<?php

namespace App\Application\Notifications;

use App\Application\Budgeting\BudgetService;
use App\Application\Insights\CategoryAwareProjectedSpendCalculator;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Shared\Time\Clock;
use App\Domain\Shared\Time\MonthlyPeriod;
use App\Models\Category;
use App\Models\User;
use Brick\Math\BigDecimal;

final readonly class NotificationEvaluationService
{
    public function __construct(
        private BudgetService $budgets,
        private CategoryAwareProjectedSpendCalculator $forecast,
        private NotificationPreferences $preferences,
        private NotificationService $notifications,
        private Clock $clock,
    ) {}

    public function evaluate(User $user, ?string $requestId = null): void
    {
        $now = $this->clock->now();
        $localNow = $now->setTimezone(new \DateTimeZone($user->budget_timezone));
        $period = MonthlyPeriod::forMonth((int) $localNow->format('Y'), (int) $localNow->format('n'), $user->budget_timezone);
        $budgetPeriods = $this->budgets->periodsFor($user, $period);
        $categories = Category::query()
            ->ownedBy($user)
            ->whereIn('id', array_map(fn ($budget): string => $budget->category_id, $budgetPeriods))
            ->pluck('name', 'id');
        foreach ($budgetPeriods as $budgetPeriod) {
            if ($budgetPeriod->effective_limit_minor_units <= 0) {
                continue;
            }
            foreach ($this->preferences->budgetThresholds($user) as $threshold) {
                if (! $this->atOrAboveThreshold($budgetPeriod->actual_spent_minor_units, $budgetPeriod->effective_limit_minor_units, $threshold)) {
                    continue;
                }
                $this->notifications->create(
                    $user,
                    NotificationType::BudgetThreshold,
                    "budget-threshold:{$budgetPeriod->getKey()}:{$threshold}",
                    'Budget threshold reached',
                    'A budget category reached a configured spending threshold.',
                    [
                        'category_id' => $budgetPeriod->category_id,
                        'category_name' => $categories->get($budgetPeriod->category_id),
                        'budget_period_id' => $budgetPeriod->getKey(),
                        'period_year' => $budgetPeriod->period_year,
                        'period_month' => $budgetPeriod->period_month,
                        'threshold_percent' => $threshold,
                        'actual_spent_minor_units' => $budgetPeriod->actual_spent_minor_units,
                        'effective_limit_minor_units' => $budgetPeriod->effective_limit_minor_units,
                        'currency_code' => $budgetPeriod->currency_code,
                        'rule' => ['name' => 'budget_threshold', 'version' => 1],
                    ],
                    requestId: $requestId,
                );
            }
        }
        $forecast = $this->forecast->calculate($user, $period);
        $periodKey = $period->year.'-'.$period->month;
        if ($forecast['actual_overspend_alert_eligible'] === true) {
            $this->notifications->create(
                $user,
                NotificationType::ActualBudgetOverspend,
                "actual-budget-overspend:{$periodKey}",
                'Budget overspent',
                'Your current posted spending exceeds the effective budget.',
                ['period' => $forecast['period'], 'actual_spend_minor_units' => $forecast['actual_spend_minor_units'], 'effective_total_budget_minor_units' => $forecast['effective_total_budget_minor_units']],
                $forecast,
                $period->startsAt(),
                $period->endsAt(),
                $requestId,
            );
        }
        if ($forecast['forecast_overspend_alert_eligible'] === true) {
            $this->notifications->create(
                $user,
                NotificationType::ProjectedBudgetOverspend,
                "projected-budget-overspend:{$periodKey}",
                'Projected budget overspend',
                'Your category-aware month-end projection exceeds the effective budget.',
                ['period' => $forecast['period'], 'projected_month_end_spend_minor_units' => $forecast['projected_month_end_spend_minor_units'], 'effective_total_budget_minor_units' => $forecast['effective_total_budget_minor_units']],
                $forecast,
                $period->startsAt(),
                $period->endsAt(),
                $requestId,
            );
        }
    }

    private function atOrAboveThreshold(int $actual, int $effectiveLimit, int $threshold): bool
    {
        return BigDecimal::of($actual)
            ->multipliedBy(100)
            ->isGreaterThanOrEqualTo(BigDecimal::of($effectiveLimit)->multipliedBy($threshold));
    }
}
