<?php

namespace App\Application\Budgeting;

use App\Application\Notifications\NotificationService;
use App\Domain\Budgeting\Enums\BudgetAdjustmentType;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Shared\Exceptions\DomainErrorCode;
use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Shared\Time\Clock;
use App\Domain\Shared\Time\MonthlyPeriod;
use App\Models\AuditEvent;
use App\Models\BudgetAdjustment;
use App\Models\BudgetPeriod;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class BudgetService
{
    public function __construct(
        private EnsureBudgetPeriodExists $periods,
        private Clock $clock,
        private NotificationService $notifications,
    ) {}

    /** @return array<int, BudgetPeriod> */
    public function periodsFor(User $user, MonthlyPeriod $period): array
    {
        $categories = $user->categories()
            ->where('kind', 'expense')
            ->where('budget_enabled', true)
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get();

        return $categories->map(fn (Category $category): BudgetPeriod => $this->periods->forCategory($user, $category, $period))->all();
    }

    public function borrowNextMonth(User $user, Category $category, MonthlyPeriod $sourcePeriod): BudgetPeriod
    {
        $source = DB::transaction(function () use ($user, $category, $sourcePeriod): BudgetPeriod {
            $source = $this->periods->forCategory($user, $category, $sourcePeriod);
            $targetPeriod = MonthlyPeriod::containing($sourcePeriod->endsAt(), $sourcePeriod->timezone->getName());
            $target = $this->periods->forCategory($user, $category, $targetPeriod);
            $locked = BudgetPeriod::query()->whereIn('id', [$source->getKey(), $target->getKey()])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            /** @var BudgetPeriod $source */
            $source = $locked->get($source->getKey());
            /** @var BudgetPeriod $target */
            $target = $locked->get($target->getKey());
            if (! $source->borrowing_enabled_snapshot) {
                throw DomainException::for(DomainErrorCode::UnauthorizedAction, 'Borrowing is not enabled for this budget category and period.');
            }
            if (BudgetAdjustment::query()->where('category_id', $category->getKey())->where('target_period_id', $target->getKey())->where('type', BudgetAdjustmentType::BorrowingReserved->value)->exists()) {
                throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'The immediate following budget period is already reserved for borrowing.');
            }
            if (BudgetAdjustment::query()->where('budget_period_id', $source->getKey())->where('type', BudgetAdjustmentType::BorrowingReserved->value)->exists()) {
                throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'A borrowed future period cannot borrow again.');
            }

            $amount = $source->base_limit_minor_units;
            if ($amount <= 0) {
                throw DomainException::for(DomainErrorCode::InvalidMoney, 'A positive base budget limit is required to borrow.');
            }
            $confirmedAt = $this->clock->now();
            /** @var BudgetAdjustment $in */
            $in = BudgetAdjustment::query()->create([
                'user_id' => $user->getKey(), 'category_id' => $category->getKey(), 'budget_period_id' => $source->getKey(),
                'type' => BudgetAdjustmentType::BorrowingIn->value, 'amount_minor_units' => $amount, 'currency_code' => $source->currency_code,
                'source_period_id' => $source->getKey(), 'target_period_id' => $target->getKey(), 'base_limit_snapshot_minor_units' => $amount,
                'actor_user_id' => $user->getKey(), 'confirmed_at' => $confirmedAt, 'reason' => 'Confirmed full-limit borrowing from the immediate next period.',
            ]);
            /** @var BudgetAdjustment $reserved */
            $reserved = BudgetAdjustment::query()->create([
                'user_id' => $user->getKey(), 'category_id' => $category->getKey(), 'budget_period_id' => $target->getKey(),
                'type' => BudgetAdjustmentType::BorrowingReserved->value, 'amount_minor_units' => -$amount, 'currency_code' => $target->currency_code,
                'source_period_id' => $source->getKey(), 'target_period_id' => $target->getKey(), 'base_limit_snapshot_minor_units' => $amount,
                'actor_user_id' => $user->getKey(), 'confirmed_at' => $confirmedAt, 'reason' => 'Reserved full-limit deduction for confirmed borrowing.',
                'paired_adjustment_id' => $in->getKey(),
            ]);
            $in->forceFill(['paired_adjustment_id' => $reserved->getKey()])->save();
            $source = $this->periods->refresh($source);
            $this->periods->refresh($target);
            $this->audit($user, 'budget.borrowing_confirmed', $source, ['target_period_id' => $target->getKey(), 'borrowing_in_adjustment_id' => $in->getKey(), 'borrowing_reserved_adjustment_id' => $reserved->getKey(), 'amount_minor_units' => $amount]);

            return $source;
        }, attempts: 3);
        $this->notifications->create(
            $user,
            NotificationType::BorrowingConsequence,
            'borrowing-consequence:'.$source->getKey(),
            'Next budget period reserved',
            'A confirmed budget borrow has reserved the immediate following period.',
            [
                'category_id' => $source->category_id,
                'budget_period_id' => $source->getKey(),
                'period_year' => $source->period_year,
                'period_month' => $source->period_month,
                'base_limit_minor_units' => $source->base_limit_minor_units,
                'currency_code' => $source->currency_code,
                'rule' => ['name' => 'borrowing_consequence', 'version' => 1],
            ],
        );

        return $source;
    }

    public function reallocate(User $user, Category $sourceCategory, Category $targetCategory, MonthlyPeriod $period, int $amount): BudgetPeriod
    {
        return DB::transaction(function () use ($user, $sourceCategory, $targetCategory, $period, $amount): BudgetPeriod {
            if ($amount <= 0 || $sourceCategory->getKey() === $targetCategory->getKey()) {
                throw DomainException::for(DomainErrorCode::InvalidMoney, 'Reallocation requires a positive amount and a different target category.');
            }
            $source = $this->periods->forCategory($user, $sourceCategory, $period);
            $target = $this->periods->forCategory($user, $targetCategory, $period);
            if ($source->currency_code !== $target->currency_code) {
                throw DomainException::for(DomainErrorCode::CurrencyMismatch, 'Reallocation requires budgets in the same currency.');
            }
            $locked = BudgetPeriod::query()->whereIn('id', [$source->getKey(), $target->getKey()])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            /** @var BudgetPeriod $source */
            $source = $locked->get($source->getKey());
            /** @var BudgetPeriod $target */
            $target = $locked->get($target->getKey());
            if ($source->status !== 'open' || $target->status !== 'open') {
                throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'Budget reallocations are only available in open periods.');
            }
            /** @var BudgetAdjustment $out */
            $out = BudgetAdjustment::query()->create([
                'user_id' => $user->getKey(), 'category_id' => $sourceCategory->getKey(), 'budget_period_id' => $source->getKey(),
                'type' => BudgetAdjustmentType::ReallocationOut->value, 'amount_minor_units' => -$amount, 'currency_code' => $source->currency_code,
                'source_period_id' => $source->getKey(), 'target_period_id' => $target->getKey(), 'actor_user_id' => $user->getKey(),
                'reason' => 'Paired budget reallocation out.',
            ]);
            /** @var BudgetAdjustment $in */
            $in = BudgetAdjustment::query()->create([
                'user_id' => $user->getKey(), 'category_id' => $targetCategory->getKey(), 'budget_period_id' => $target->getKey(),
                'type' => BudgetAdjustmentType::ReallocationIn->value, 'amount_minor_units' => $amount, 'currency_code' => $target->currency_code,
                'source_period_id' => $source->getKey(), 'target_period_id' => $target->getKey(), 'actor_user_id' => $user->getKey(),
                'reason' => 'Paired budget reallocation in.', 'paired_adjustment_id' => $out->getKey(),
            ]);
            $out->forceFill(['paired_adjustment_id' => $in->getKey()])->save();
            $source = $this->periods->refresh($source);
            $this->periods->refresh($target);
            $this->audit($user, 'budget.reallocated', $source, ['target_period_id' => $target->getKey(), 'reallocation_out_adjustment_id' => $out->getKey(), 'reallocation_in_adjustment_id' => $in->getKey(), 'amount_minor_units' => $amount]);

            return $source;
        }, attempts: 3);
    }

    /** @param array<string, int|string> $summary */
    private function audit(User $user, string $eventName, BudgetPeriod $period, array $summary): void
    {
        AuditEvent::query()->create([
            'actor_user_id' => $user->getKey(), 'user_id' => $user->getKey(), 'event_name' => $eventName,
            'aggregate_type' => 'budget_period', 'aggregate_id' => $period->getKey(), 'summary' => $summary,
        ]);
    }
}
