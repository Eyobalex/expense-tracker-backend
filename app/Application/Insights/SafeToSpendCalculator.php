<?php

namespace App\Application\Insights;

use App\Application\Budgeting\BudgetService;
use App\Domain\Insights\Enums\FormulaName;
use App\Domain\Insights\InsightFormula;
use App\Domain\Shared\Time\Clock;
use App\Domain\Shared\Time\MonthlyPeriod;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final readonly class SafeToSpendCalculator
{
    public function __construct(
        private BudgetService $budgets,
        private Clock $clock,
    ) {}

    /** @return array<string, mixed> */
    public function calculate(User $user, MonthlyPeriod $period): array
    {
        $insightPeriod = InsightPeriod::forMonth($period, $this->clock->now());
        $budgets = $this->budgets->periodsFor($user, $period);
        $categories = array_map(static fn ($budget): array => [
            'category_id' => $budget->category_id,
            'effective_limit_minor_units' => $budget->effective_limit_minor_units,
            'actual_spent_minor_units' => $budget->actual_spent_minor_units,
            'remaining_minor_units' => $budget->remaining_minor_units,
            'status' => $budget->remaining_minor_units < 0 ? 'overspent' : 'available',
        ], $budgets);
        $totalRemaining = array_sum(array_column($categories, 'remaining_minor_units'));
        $common = [
            'formula' => InsightFormula::identity(FormulaName::SafeToSpend),
            'period' => $this->periodMetadata($period),
            'timezone' => $period->timezone->getName(),
            'base_currency_code' => $user->base_currency_code,
            'calculated_at' => $insightPeriod->calculatedAt->format(DATE_ATOM),
            'total_remaining_minor_units' => $totalRemaining,
            'categories' => $categories,
        ];
        if ($categories === []) {
            return [...$common, 'value_minor_units' => null, 'status' => 'not_available', 'reason' => 'no_active_budgets', 'remaining_calendar_days_including_today' => null];
        }

        $remainingDays = $insightPeriod->remainingDaysIncludingToday();
        $value = BigDecimal::of(max(0, $totalRemaining))
            ->dividedBy($remainingDays, 12, RoundingMode::HalfEven)
            ->toScale(0, RoundingMode::HalfEven)
            ->toInt();

        return [...$common, 'value_minor_units' => $value, 'status' => 'available', 'reason' => null, 'remaining_calendar_days_including_today' => $remainingDays];
    }

    /** @return array{year: int, month: int, period_start_at: string, period_end_at: string} */
    private function periodMetadata(MonthlyPeriod $period): array
    {
        return ['year' => $period->year, 'month' => $period->month, 'period_start_at' => $period->startsAt()->format(DATE_ATOM), 'period_end_at' => $period->endsAt()->format(DATE_ATOM)];
    }
}
