<?php

namespace App\Application\Insights;

use App\Domain\Shared\Time\Clock;
use App\Domain\Shared\Time\MonthlyPeriod;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class DashboardService
{
    public function __construct(
        private SafeToSpendCalculator $safeToSpend,
        private CategoryAwareProjectedSpendCalculator $forecast,
        private IncomeConcentrationCalculator $incomeConcentration,
        private PostedExpenseAllocationRepository $allocations,
        private Clock $clock,
    ) {}

    /** @return array<string, mixed> */
    public function summary(User $user, MonthlyPeriod $period): array
    {
        $current = InsightPeriod::forMonth($period, $this->clock->now());
        $income = (int) DB::table('financial_transactions')
            ->where('user_id', $user->getKey())
            ->where('state', 'posted')
            ->where('type', 'income')
            ->where('occurred_at', '>=', $period->startsAt())
            ->where('occurred_at', '<', $period->endsAt())
            ->sum('base_amount_minor_units');
        $expenses = (int) DB::table('financial_transactions')
            ->where('user_id', $user->getKey())
            ->where('state', 'posted')
            ->whereIn('type', ['expense', 'fee', 'credit_card_purchase'])
            ->where('occurred_at', '>=', $period->startsAt())
            ->where('occurred_at', '<', $period->endsAt())
            ->sum('base_amount_minor_units');
        $totalBalance = (int) DB::table('journal_lines')
            ->join('financial_accounts', 'financial_accounts.id', '=', 'journal_lines.financial_account_id')
            ->where('journal_lines.user_id', $user->getKey())
            ->selectRaw("COALESCE(SUM(CASE WHEN financial_accounts.accounting_type = 'asset' THEN journal_lines.base_debit_minor_units - journal_lines.base_credit_minor_units ELSE journal_lines.base_credit_minor_units - journal_lines.base_debit_minor_units END), 0) as balance")
            ->value('balance');
        $categorySpend = $this->allocations->allocations($user, $period->startsAt(), $period->endsAt())
            ->groupBy('category_id')
            ->map(fn ($allocations): int => (int) $allocations->sum('amount_minor_units'))
            ->sortDesc()
            ->take(5);
        $categoryNames = Category::query()->ownedBy($user)->whereIn('id', $categorySpend->keys())->pluck('name', 'id');
        $topCategories = $categorySpend->map(fn (int $amount, string $categoryId): array => [
            'category_id' => $categoryId,
            'name' => $categoryNames->get($categoryId),
            'spend_minor_units' => $amount,
        ])->values()->all();

        return [
            'period' => ['year' => $period->year, 'month' => $period->month, 'period_start_at' => $period->startsAt()->format(DATE_ATOM), 'period_end_at' => $period->endsAt()->format(DATE_ATOM)],
            'timezone' => $period->timezone->getName(),
            'base_currency_code' => $user->base_currency_code,
            'calculated_at' => $current->calculatedAt->format(DATE_ATOM),
            'total_balance_minor_units' => $totalBalance,
            'income_minor_units' => $income,
            'expense_minor_units' => $expenses,
            'pending_review_count' => $user->financialTransactions()->where('state', 'pending_review')->count(),
            'top_spending_categories' => $topCategories,
            'safe_to_spend' => $this->safeToSpend->calculate($user, $period),
            'projected_month_end' => $this->forecast->calculate($user, $period),
            'income_concentration' => $this->incomeConcentration->calculate($user),
            'item_price_movement_previews' => [],
        ];
    }
}
