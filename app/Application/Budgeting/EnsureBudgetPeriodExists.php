<?php

namespace App\Application\Budgeting;

use App\Domain\Budgeting\Enums\BudgetAdjustmentType;
use App\Domain\Shared\Time\Clock;
use App\Domain\Shared\Time\MonthlyPeriod;
use App\Models\BudgetAdjustment;
use App\Models\BudgetPeriod;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class EnsureBudgetPeriodExists
{
    public function __construct(
        private BudgetActualSpentCalculator $actualSpent,
        private Clock $clock,
    ) {}

    public function forCategory(User $user, Category $category, MonthlyPeriod $period): BudgetPeriod
    {
        return DB::transaction(function () use ($user, $category, $period): BudgetPeriod {
            /** @var Category $lockedCategory */
            $lockedCategory = Category::query()->ownedBy($user)->whereKey($category->getKey())->lockForUpdate()->firstOrFail();
            /** @var BudgetPeriod|null $existing */
            $existing = BudgetPeriod::query()
                ->where('category_id', $lockedCategory->getKey())
                ->where('period_year', $period->year)
                ->where('period_month', $period->month)
                ->lockForUpdate()
                ->first();
            if ($existing instanceof BudgetPeriod) {
                return $this->refresh($existing);
            }

            $previous = $this->previousPeriod($user, $lockedCategory, $period);
            $baseLimit = (int) ($lockedCategory->base_limit_minor_units ?? 0);
            $timezone = $period->timezone->getName();
            /** @var BudgetPeriod $created */
            $created = BudgetPeriod::query()->create([
                'user_id' => $user->getKey(),
                'category_id' => $lockedCategory->getKey(),
                'period_year' => $period->year,
                'period_month' => $period->month,
                'budget_timezone' => $timezone,
                'period_start_at' => $period->startsAt(),
                'period_end_at' => $period->endsAt(),
                'status' => 'open',
                'currency_code' => $lockedCategory->budget_currency_code ?? $user->base_currency_code,
                'base_limit_minor_units' => $baseLimit,
                'rollover_enabled_snapshot' => $lockedCategory->rollover_enabled,
                'overspend_carry_enabled_snapshot' => $lockedCategory->overspend_carry_enabled,
                'borrowing_enabled_snapshot' => $lockedCategory->borrowing_enabled,
                'initialized_at' => $this->clock->now(),
            ]);

            $this->closeExpiredPreviousPeriod($previous);
            if ($previous instanceof BudgetPeriod) {
                $this->seedCarryAdjustments($created, $previous);
            }

            return $this->refresh($created);
        }, attempts: 3);
    }

    public function refresh(BudgetPeriod $period): BudgetPeriod
    {
        $actual = $this->actualSpent->calculate($period);
        $adjustments = $period->adjustments()->get();
        $borrowingDeduction = abs((int) $adjustments->where('type', BudgetAdjustmentType::BorrowingReserved->value)->sum('amount_minor_units'));
        $positiveRollover = max(0, (int) $adjustments->where('type', BudgetAdjustmentType::Rollover->value)->sum('amount_minor_units'));
        $negativeCarry = abs(min(0, (int) $adjustments->where('type', BudgetAdjustmentType::Underflow->value)->sum('amount_minor_units')));
        $reallocationIn = max(0, (int) $adjustments->where('type', BudgetAdjustmentType::ReallocationIn->value)->sum('amount_minor_units'));
        $reallocationOut = abs(min(0, (int) $adjustments->where('type', BudgetAdjustmentType::ReallocationOut->value)->sum('amount_minor_units')));
        $corrections = (int) $adjustments->where('type', BudgetAdjustmentType::Correction->value)->sum('amount_minor_units');
        $borrowingIn = max(0, (int) $adjustments->where('type', BudgetAdjustmentType::BorrowingIn->value)->sum('amount_minor_units'));
        $effective = $period->base_limit_minor_units + $borrowingIn - $borrowingDeduction + $positiveRollover - $negativeCarry + $reallocationIn - $reallocationOut + $corrections;

        $period->forceFill([
            'borrowing_deduction_minor_units' => $borrowingDeduction,
            'positive_rollover_minor_units' => $positiveRollover,
            'negative_carry_minor_units' => $negativeCarry,
            'reallocation_in_minor_units' => $reallocationIn,
            'reallocation_out_minor_units' => $reallocationOut,
            'effective_limit_minor_units' => $effective,
            'actual_spent_minor_units' => $actual,
            'remaining_minor_units' => $effective - $actual,
        ])->save();

        return $period->refresh();
    }

    private function closeExpiredPreviousPeriod(?BudgetPeriod $period): void
    {
        if ($period instanceof BudgetPeriod && $period->status === 'open' && $period->period_end_at <= $this->clock->now()) {
            $period->forceFill(['status' => 'closed', 'closed_at' => $this->clock->now()])->save();
        }
    }

    private function previousPeriod(User $user, Category $category, MonthlyPeriod $period): ?BudgetPeriod
    {
        $previousStart = $period->startsAt()->modify('-1 month');
        $previous = MonthlyPeriod::containing($previousStart, $period->timezone->getName());

        return BudgetPeriod::query()
            ->where('user_id', $user->getKey())
            ->where('category_id', $category->getKey())
            ->where('period_year', $previous->year)
            ->where('period_month', $previous->month)
            ->lockForUpdate()
            ->first();
    }

    private function seedCarryAdjustments(BudgetPeriod $current, BudgetPeriod $previous): void
    {
        $previous = $this->refresh($previous);
        if ($previous->rollover_enabled_snapshot && $previous->remaining_minor_units > 0) {
            BudgetAdjustment::query()->create([
                'user_id' => $current->user_id,
                'category_id' => $current->category_id,
                'budget_period_id' => $current->getKey(),
                'type' => BudgetAdjustmentType::Rollover->value,
                'amount_minor_units' => $previous->remaining_minor_units,
                'currency_code' => $current->currency_code,
                'source_period_id' => $previous->getKey(),
                'reason' => 'Initialized positive rollover from the preceding period.',
            ]);
        }
        if ($previous->overspend_carry_enabled_snapshot && $previous->remaining_minor_units < 0) {
            BudgetAdjustment::query()->create([
                'user_id' => $current->user_id,
                'category_id' => $current->category_id,
                'budget_period_id' => $current->getKey(),
                'type' => BudgetAdjustmentType::Underflow->value,
                'amount_minor_units' => $previous->remaining_minor_units,
                'currency_code' => $current->currency_code,
                'source_period_id' => $previous->getKey(),
                'reason' => 'Initialized negative carry from the preceding period.',
            ]);
        }
    }
}
