<?php

namespace App\Application\Budgeting;

use App\Domain\Budgeting\Enums\BudgetAdjustmentType;
use App\Domain\Shared\Time\MonthlyPeriod;
use App\Models\BudgetAdjustment;
use App\Models\BudgetPeriod;
use App\Models\Category;
use App\Models\FinancialTransaction;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class RecalculateBudgetChain
{
    public function __construct(private EnsureBudgetPeriodExists $periods) {}

    public function forTransaction(FinancialTransaction $transaction): void
    {
        if ($transaction->state !== 'posted') {
            return;
        }

        $this->forTransactionCategories($transaction);
    }

    public function forTransactionEffectRemoved(FinancialTransaction $transaction): void
    {
        $this->forTransactionCategories($transaction);
    }

    private function forTransactionCategories(FinancialTransaction $transaction): void
    {
        /** @var User $user */
        $user = $transaction->user;
        foreach ($this->categoryIdsFor($transaction) as $categoryId) {
            /** @var Category|null $category */
            $category = $user->categories()->whereKey($categoryId)->first();
            if (! $category instanceof Category || ! $category->budget_enabled || $category->kind !== 'expense') {
                continue;
            }
            $period = MonthlyPeriod::containing($this->effectiveBudgetOccurredAt($transaction), $user->budget_timezone);
            $this->fromPeriod($user, $category, $period, $transaction);
        }
    }

    private function effectiveBudgetOccurredAt(FinancialTransaction $transaction): DateTimeInterface
    {
        if ($transaction->type === 'refund' && $transaction->relatedTransaction !== null) {
            return $transaction->relatedTransaction->occurred_at;
        }

        return $transaction->occurred_at;
    }

    public function fromPeriod(User $user, Category $category, MonthlyPeriod $firstPeriod, ?FinancialTransaction $sourceTransaction = null): void
    {
        DB::transaction(function () use ($user, $category, $firstPeriod, $sourceTransaction): void {
            $initial = $this->periods->forCategory($user, $category, $firstPeriod);
            /** @var Collection<int, BudgetPeriod> $chain */
            $chain = BudgetPeriod::query()
                ->where('user_id', $user->getKey())
                ->where('category_id', $category->getKey())
                ->where(function ($query) use ($initial): void {
                    $query->where('period_year', '>', $initial->period_year)
                        ->orWhere(function ($sameYear) use ($initial): void {
                            $sameYear->where('period_year', $initial->period_year)->where('period_month', '>=', $initial->period_month);
                        });
                })
                ->orderBy('period_year')
                ->orderBy('period_month')
                ->lockForUpdate()
                ->get();
            foreach ($chain as $period) {
                $this->compensateIncomingCarry($period, $sourceTransaction);
                $this->periods->refresh($period);
            }
        }, attempts: 3);
    }

    private function compensateIncomingCarry(BudgetPeriod $period, ?FinancialTransaction $sourceTransaction): void
    {
        $previous = BudgetPeriod::query()
            ->where('category_id', $period->category_id)
            ->where('period_end_at', $period->period_start_at)
            ->lockForUpdate()
            ->first();
        if (! $previous instanceof BudgetPeriod) {
            return;
        }
        $previous = $this->periods->refresh($previous);
        $desired = $previous->remaining_minor_units >= 0
            ? ($previous->rollover_enabled_snapshot ? $previous->remaining_minor_units : 0)
            : ($previous->overspend_carry_enabled_snapshot ? $previous->remaining_minor_units : 0);
        $existing = (int) BudgetAdjustment::query()
            ->where('budget_period_id', $period->getKey())
            ->where('source_period_id', $previous->getKey())
            ->whereIn('type', [BudgetAdjustmentType::Rollover->value, BudgetAdjustmentType::Underflow->value, BudgetAdjustmentType::Correction->value])
            ->sum('amount_minor_units');
        $difference = $desired - $existing;
        if ($difference === 0) {
            return;
        }
        BudgetAdjustment::query()->create([
            'user_id' => $period->user_id,
            'category_id' => $period->category_id,
            'budget_period_id' => $period->getKey(),
            'type' => BudgetAdjustmentType::Correction->value,
            'amount_minor_units' => $difference,
            'currency_code' => $period->currency_code,
            'source_period_id' => $previous->getKey(),
            'source_transaction_id' => $sourceTransaction?->getKey(),
            'reason' => 'Immutable historical carry compensation after a corrected financial effect.',
        ]);
    }

    /** @return list<string> */
    private function categoryIdsFor(FinancialTransaction $transaction): array
    {
        if ($transaction->type === 'refund' && $transaction->relatedTransaction?->category_id !== null) {
            return [$transaction->relatedTransaction->category_id];
        }
        $categoryIds = $transaction->splits->pluck('category_id')->filter()->values()->all();
        if ($transaction->category_id !== null) {
            $categoryIds[] = $transaction->category_id;
        }

        return array_values(array_unique($categoryIds));
    }
}
