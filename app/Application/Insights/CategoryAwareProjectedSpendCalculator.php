<?php

namespace App\Application\Insights;

use App\Application\Budgeting\BudgetService;
use App\Domain\Insights\Enums\ForecastBehavior;
use App\Domain\Insights\Enums\FormulaName;
use App\Domain\Insights\InsightFormula;
use App\Domain\Shared\Time\Clock;
use App\Domain\Shared\Time\MonthlyPeriod;
use App\Models\BudgetPeriod;
use App\Models\Category;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use Illuminate\Support\Collection;

/** @phpstan-type Allocation array{category_id: string, transaction_id: string, transaction_type: string, occurred_at: string, effective_occurred_at: string, amount_minor_units: int} */
final readonly class CategoryAwareProjectedSpendCalculator
{
    public function __construct(
        private BudgetService $budgets,
        private PostedExpenseAllocationRepository $allocations,
        private Clock $clock,
    ) {}

    /** @return array<string, mixed> */
    public function calculate(User $user, MonthlyPeriod $period): array
    {
        $insightPeriod = InsightPeriod::forMonth($period, $this->clock->now());
        $budgetPeriods = collect($this->budgets->periodsFor($user, $period));
        $categories = Category::query()->ownedBy($user)
            ->whereIn('id', $budgetPeriods->pluck('category_id'))
            ->orderBy('name')
            ->get()
            ->keyBy('id');
        $historyStart = $insightPeriod->localDayStart()->modify('-119 days');
        $allocations = $this->allocations->allocations($user, $historyStart, $insightPeriod->localDayEnd());
        $allocationsByCategory = $allocations->groupBy('category_id');
        $historicalPeriods = $this->historicalBudgetPeriods($user, array_values($budgetPeriods->pluck('category_id')->map(fn (mixed $categoryId): string => (string) $categoryId)->all()), $period, $insightPeriod);
        $projections = [];
        foreach ($budgetPeriods as $budgetPeriod) {
            /** @var Category|null $category */
            $category = $categories->get($budgetPeriod->category_id);
            if (! $category instanceof Category) {
                continue;
            }
            /** @var Collection<int, Allocation> $categoryAllocations */
            $categoryAllocations = $allocationsByCategory->get($category->getKey(), collect());
            $projections[] = match (ForecastBehavior::from($category->forecast_behavior)) {
                ForecastBehavior::Fixed => $this->fixedProjection($category, $budgetPeriod->actual_spent_minor_units, $historicalPeriods->get($category->getKey(), collect()), $period),
                ForecastBehavior::Periodic => $this->periodicProjection($category, $budgetPeriod->actual_spent_minor_units, $categoryAllocations, $insightPeriod, $period),
                ForecastBehavior::Variable => $this->variableProjection($category, $budgetPeriod->actual_spent_minor_units, $categoryAllocations, $insightPeriod),
            };
        }
        $actual = array_sum(array_column($projections, 'current_actual_minor_units'));
        $expected = array_sum(array_column($projections, 'expected_remaining_minor_units'));
        $projected = array_sum(array_column($projections, 'projected_amount_minor_units'));
        $effectiveBudget = $budgetPeriods->sum('effective_limit_minor_units');

        return [
            'formula' => InsightFormula::identity(FormulaName::CategoryAwareProjectedSpend),
            'period' => $this->periodMetadata($period),
            'timezone' => $period->timezone->getName(),
            'base_currency_code' => $user->base_currency_code,
            'calculated_at' => $insightPeriod->calculatedAt->format(DATE_ATOM),
            'actual_spend_minor_units' => $actual,
            'expected_remaining_minor_units' => $expected,
            'projected_month_end_spend_minor_units' => $projected,
            'effective_total_budget_minor_units' => $effectiveBudget,
            'projected_budget_difference_minor_units' => $effectiveBudget - $projected,
            'elapsed_budget_calendar_days' => $insightPeriod->elapsedCalendarDays(),
            'forecast_overspend_alert_eligible' => $insightPeriod->elapsedCalendarDays() >= 5 && $projected > $effectiveBudget,
            'actual_overspend_alert_eligible' => $actual > $effectiveBudget,
            'categories' => $projections,
        ];
    }

    /**
     * @param  list<string>  $categoryIds
     * @return Collection<string, Collection<int, BudgetPeriod>>
     */
    private function historicalBudgetPeriods(User $user, array $categoryIds, MonthlyPeriod $current, InsightPeriod $insightPeriod): Collection
    {
        $periods = BudgetPeriod::query()
            ->ownedBy($user)
            ->whereIn('category_id', $categoryIds)
            ->where('period_end_at', '<=', $insightPeriod->calculatedAt)
            ->where(function ($query) use ($current): void {
                $query->where('period_year', '<', $current->year)
                    ->orWhere(function ($monthQuery) use ($current): void {
                        $monthQuery->where('period_year', $current->year)->where('period_month', '<', $current->month);
                    });
            })
            ->where('actual_spent_minor_units', '>', 0)
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->get();
        $grouped = [];
        foreach ($periods as $budgetPeriod) {
            $categoryId = $budgetPeriod->category_id;
            if (! isset($grouped[$categoryId])) {
                $grouped[$categoryId] = new Collection([$budgetPeriod]);

                continue;
            }
            if ($grouped[$categoryId]->count() < 3) {
                $grouped[$categoryId] = $grouped[$categoryId]->concat([$budgetPeriod])->values();
            }
        }

        return new Collection($grouped);
    }

    /**
     * @param  Collection<int, BudgetPeriod>  $history
     * @return array<string, mixed>
     */
    private function fixedProjection(Category $category, int $currentActual, Collection $history, MonthlyPeriod $period): array
    {
        $baseline = $history->isEmpty() ? null : $this->median(array_values($history->pluck('actual_spent_minor_units')->map(fn (mixed $amount): string => (string) $amount)->all()));
        $projected = $baseline === null ? $currentActual : max($currentActual, $this->roundMinorUnits($baseline));
        $expected = $baseline === null ? 0 : max(0, $this->roundMinorUnits($baseline) - $currentActual);

        return $this->projection($category, ForecastBehavior::Fixed, 'fixed', $baseline === null ? 'insufficient_history' : 'established', [
            'completed_periods' => $history->count(),
            'period_start_at' => $period->startsAt()->format(DATE_ATOM),
            'period_end_at' => $period->endsAt()->format(DATE_ATOM),
        ], $currentActual, $expected, $projected);
    }

    /**
     * @param  Collection<int, Allocation>  $allocations
     * @return array<string, mixed>
     */
    private function periodicProjection(Category $category, int $currentActual, Collection $allocations, InsightPeriod $insightPeriod, MonthlyPeriod $period): array
    {
        $occurrences = $this->positiveDailyOccurrences($allocations, $period->timezone->getName());
        if ($occurrences->count() < 3) {
            return $this->variableProjection($category, $currentActual, $allocations, $insightPeriod, 'variable_fallback', 'insufficient_periodic_history');
        }
        $amount = $this->median(array_values($occurrences->pluck('amount_minor_units')->map(fn (mixed $value): string => (string) $value)->all()));
        $dates = $occurrences->pluck('local_date')->all();
        $intervals = [];
        for ($index = 1; $index < count($dates); $index++) {
            $interval = (int) $dates[$index - 1]->diff($dates[$index])->format('%a');
            if ($interval > 0) {
                $intervals[] = (string) $interval;
            }
        }
        if ($intervals === []) {
            return $this->variableProjection($category, $currentActual, $allocations, $insightPeriod, 'variable_fallback', 'insufficient_periodic_history');
        }
        $intervalDays = $this->roundMinorUnits($this->median($intervals));
        if ($intervalDays <= 0) {
            return $this->variableProjection($category, $currentActual, $allocations, $insightPeriod, 'variable_fallback', 'insufficient_periodic_history');
        }
        /** @var DateTimeImmutable $prediction */
        $prediction = $dates[array_key_last($dates)];
        $count = 0;
        $today = $insightPeriod->localDayStart();
        while (true) {
            $prediction = $prediction->modify("+{$intervalDays} days");
            if ($prediction <= $today) {
                continue;
            }
            if ($prediction >= $period->endsAt()) {
                break;
            }
            $count++;
        }
        $expected = $this->roundMinorUnits(BigDecimal::of($amount)->multipliedBy($count));

        return $this->projection($category, ForecastBehavior::Periodic, 'periodic', 'established', [
            'lookback_days' => 120,
            'qualifying_occurrences' => $occurrences->count(),
            'typical_interval_days' => $intervalDays,
            'expected_occurrence_count' => $count,
        ], $currentActual, $expected, $currentActual + $expected);
    }

    /**
     * @param  Collection<int, Allocation>  $allocations
     * @return array<string, mixed>
     */
    private function variableProjection(Category $category, int $currentActual, Collection $allocations, InsightPeriod $insightPeriod, string $method = 'variable', string $quality = 'preliminary'): array
    {
        $windowStart = $insightPeriod->localDayStart()->modify('-29 days');
        $windowAllocations = $allocations->filter(fn (array $allocation): bool => new DateTimeImmutable($allocation['effective_occurred_at']) >= $windowStart);
        $first = $windowAllocations->first();
        $coveredDays = 30;
        if ($first !== null) {
            $firstDate = (new DateTimeImmutable($first['effective_occurred_at']))->setTimezone($insightPeriod->budgetPeriod->timezone)->setTime(0, 0);
            $coveredDays = min(30, max(1, (int) $firstDate->diff($insightPeriod->localDayStart())->format('%a') + 1));
        }
        $recentSpend = (int) $windowAllocations->sum('amount_minor_units');
        $expected = $this->roundMinorUnits(BigDecimal::of($recentSpend)->dividedBy($coveredDays, 12, RoundingMode::HalfEven)->multipliedBy($insightPeriod->remainingDaysAfterToday()));

        return $this->projection($category, ForecastBehavior::Variable, $method, $quality, [
            'lookback_days' => 30,
            'covered_calendar_days' => $coveredDays,
            'remaining_calendar_days_after_today' => $insightPeriod->remainingDaysAfterToday(),
        ], $currentActual, $expected, $currentActual + $expected);
    }

    /**
     * @param  Collection<int, Allocation>  $allocations
     * @return Collection<int, array{local_date: DateTimeImmutable, amount_minor_units: int}>
     */
    private function positiveDailyOccurrences(Collection $allocations, string $timezone): Collection
    {
        return $allocations
            ->filter(fn (array $allocation): bool => $allocation['transaction_type'] !== 'refund' && $allocation['amount_minor_units'] > 0)
            ->groupBy(fn (array $allocation): string => (new DateTimeImmutable($allocation['effective_occurred_at']))->setTimezone(new \DateTimeZone($timezone))->format('Y-m-d'))
            ->map(function (Collection $dayAllocations) use ($timezone): array {
                $first = $dayAllocations->first();

                return ['local_date' => (new DateTimeImmutable($first['effective_occurred_at']))->setTimezone(new \DateTimeZone($timezone))->setTime(0, 0), 'amount_minor_units' => (int) $dayAllocations->sum('amount_minor_units')];
            })
            ->sortBy('local_date')
            ->values();
    }

    /**
     * @param  array<string, int|string>  $window
     * @return array<string, mixed>
     */
    private function projection(Category $category, ForecastBehavior $behavior, string $method, string $quality, array $window, int $actual, int $expected, int $projected): array
    {
        return [
            'category_id' => $category->getKey(),
            'forecast_behavior' => $behavior->value,
            'forecast_method' => $behavior->value,
            'method_used' => $method,
            'data_quality' => $quality,
            'history_window' => $window,
            'current_actual_minor_units' => $actual,
            'expected_remaining_minor_units' => $expected,
            'projected_amount_minor_units' => $projected,
        ];
    }

    /** @param list<string> $values */
    private function median(array $values): string
    {
        usort($values, fn (string $left, string $right): int => BigDecimal::of($left)->compareTo(BigDecimal::of($right)));
        $middle = intdiv(count($values), 2);

        return count($values) % 2 === 1
            ? $values[$middle]
            : BigDecimal::of($values[$middle - 1])->plus($values[$middle])->dividedBy(2, 12, RoundingMode::HalfEven)->__toString();
    }

    private function roundMinorUnits(string|BigDecimal $amount): int
    {
        return BigDecimal::of($amount)->toScale(0, RoundingMode::HalfEven)->toInt();
    }

    /** @return array{year: int, month: int, period_start_at: string, period_end_at: string} */
    private function periodMetadata(MonthlyPeriod $period): array
    {
        return ['year' => $period->year, 'month' => $period->month, 'period_start_at' => $period->startsAt()->format(DATE_ATOM), 'period_end_at' => $period->endsAt()->format(DATE_ATOM)];
    }
}
