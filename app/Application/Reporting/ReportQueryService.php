<?php

namespace App\Application\Reporting;

use App\Application\Insights\IncomeConcentrationCalculator;
use App\Application\Insights\PostedExpenseAllocationRepository;
use App\Domain\Reporting\Enums\ReportType;
use App\Domain\Reporting\ReportFilter;
use App\Models\BudgetPeriod;
use App\Models\Category;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\JournalLine;
use App\Models\LineItem;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final readonly class ReportQueryService
{
    public function __construct(
        private PostedExpenseAllocationRepository $expenseAllocations,
        private IncomeConcentrationCalculator $incomeConcentration,
    ) {}

    /** @return array{title:string, columns:list<string>, rows:list<array<string, scalar|null>>, summary:array<string, mixed>, context:array<string, mixed>} */
    public function report(User $user, ReportType $type, ReportFilter $filter): array
    {
        return match ($type) {
            ReportType::Transaction => $this->transactions($user, $filter),
            ReportType::AccountStatement => $this->accountStatement($user, $filter),
            ReportType::Budget => $this->budget($user, $filter),
            ReportType::Expense => $this->expense($user, $filter),
            ReportType::Income => $this->income($user, $filter),
            ReportType::Merchant => $this->merchant($user, $filter),
            ReportType::ItemPrice => $this->itemPrices($user, $filter),
            ReportType::MultiCurrency => $this->multiCurrency($user, $filter),
            default => throw new \LogicException('Portability exports are not tabular reports.'),
        };
    }

    public function transactionCount(User $user, ReportFilter $filter): int
    {
        return $this->filteredTransactions($user, $filter)->count();
    }

    /** @return array{title:string, columns:list<string>, rows:list<array<string, scalar|null>>, summary:array<string, mixed>, context:array<string, mixed>} */
    private function transactions(User $user, ReportFilter $filter): array
    {
        $transactions = $this->filteredTransactions($user, $filter)->with(['financialAccount:id,name,currency_code', 'counterpartyAccount:id,name,currency_code', 'category:id,name', 'merchant:id,display_name', 'splits.category:id,name'])->orderBy('occurred_at')->orderBy('id')->get();
        $rows = $transactions->map(fn (FinancialTransaction $transaction): array => [
            'id' => $transaction->getKey(), 'occurred_at' => $transaction->occurred_at->toISOString(), 'type' => $transaction->type,
            'account' => $transaction->financialAccount?->name, 'counterparty_account' => $transaction->counterpartyAccount?->name,
            'category' => $transaction->category?->name, 'merchant' => $transaction->merchant->display_name ?? $transaction->raw_merchant_text,
            'original_amount_minor_units' => $transaction->original_amount_minor_units, 'original_currency_code' => $transaction->original_currency_code,
            'base_amount_minor_units' => $transaction->base_amount_minor_units, 'base_currency_code' => $transaction->base_currency_code,
            'splits' => $transaction->splits->map(fn ($split): string => trim(($split->category->name ?? 'Uncategorized').'='.$split->amount_minor_units))->join('; '),
            'state' => $transaction->state,
        ])->all();

        return $this->document('Transaction Report', array_keys($rows[0] ?? $this->transactionRowShape()), $rows, [
            'posted_transaction_count' => count($rows),
            'base_total_minor_units' => $transactions->sum('base_amount_minor_units'),
        ], $filter);
    }

    /** @return array{title:string, columns:list<string>, rows:list<array<string, scalar|null>>, summary:array<string, mixed>, context:array<string, mixed>} */
    private function accountStatement(User $user, ReportFilter $filter): array
    {
        $accounts = FinancialAccount::query()->ownedBy($user)->when($filter->filters['financial_account_id'] ?? null, fn (Builder $query, string $accountId): Builder => $query->whereKey($accountId))->orderBy('name')->get();
        $accountIds = $accounts->modelKeys();
        $lines = JournalLine::query()->where('user_id', $user->getKey())->whereIn('financial_account_id', $accountIds)->with(['financialAccount:id,name,accounting_type,currency_code', 'journalEntry.transaction'])->get();
        $from = $filter->from();
        $until = $filter->until();
        $rows = [];
        foreach ($accounts as $account) {
            $accountLines = $lines->filter(fn (JournalLine $line): bool => $line->financial_account_id === $account->getKey());
            $opening = $this->accountBalance($accountLines->filter(fn (JournalLine $line): bool => $from instanceof DateTimeInterface && $line->journalEntry?->transaction?->occurred_at < $from), $account->accounting_type);
            $periodLines = $accountLines->filter(function (JournalLine $line) use ($from, $until): bool {
                $occurredAt = $line->journalEntry?->transaction?->occurred_at;

                return $occurredAt instanceof DateTimeInterface && (! $from instanceof DateTimeInterface || $occurredAt >= $from) && (! $until instanceof DateTimeInterface || $occurredAt < $until);
            });
            $change = $this->accountBalance($periodLines, $account->accounting_type);
            $rows[] = [
                'account_id' => $account->getKey(), 'account' => $account->name, 'currency_code' => $account->currency_code,
                'opening_balance_minor_units' => $opening, 'period_change_minor_units' => $change,
                'closing_balance_minor_units' => $opening + $change, 'journal_line_count' => $periodLines->count(),
            ];
        }

        return $this->document('Account Statement', array_keys($rows[0] ?? $this->accountRowShape()), $rows, ['account_count' => count($rows)], $filter);
    }

    /** @return array{title:string, columns:list<string>, rows:list<array<string, scalar|null>>, summary:array<string, mixed>, context:array<string, mixed>} */
    private function budget(User $user, ReportFilter $filter): array
    {
        $periods = BudgetPeriod::query()->ownedBy($user)->with(['category:id,name', 'adjustments'])->when($filter->from(), fn (Builder $query, DateTimeInterface $from): Builder => $query->where('period_end_at', '>', $from))->when($filter->until(), fn (Builder $query, DateTimeInterface $until): Builder => $query->where('period_start_at', '<', $until))->orderBy('period_year')->orderBy('period_month')->orderBy('category_id')->get();
        $rows = $periods->map(fn (BudgetPeriod $period): array => [
            'budget_period_id' => $period->getKey(), 'period' => sprintf('%d-%02d', $period->period_year, $period->period_month),
            'category' => $period->category?->name, 'currency_code' => $period->currency_code, 'base_limit_minor_units' => $period->base_limit_minor_units,
            'borrowing_deduction_minor_units' => $period->borrowing_deduction_minor_units, 'positive_rollover_minor_units' => $period->positive_rollover_minor_units,
            'negative_carry_minor_units' => $period->negative_carry_minor_units, 'reallocation_in_minor_units' => $period->reallocation_in_minor_units,
            'reallocation_out_minor_units' => $period->reallocation_out_minor_units, 'effective_limit_minor_units' => $period->effective_limit_minor_units,
            'actual_spent_minor_units' => $period->actual_spent_minor_units, 'remaining_minor_units' => $period->remaining_minor_units,
            'adjustments' => $period->adjustments->map(fn ($adjustment): string => $adjustment->type.':'.$adjustment->amount_minor_units)->join('; '),
        ])->all();

        return $this->document('Budget Report', array_keys($rows[0] ?? $this->budgetRowShape()), $rows, ['period_count' => count($rows)], $filter);
    }

    /** @return array{title:string, columns:list<string>, rows:list<array<string, scalar|null>>, summary:array<string, mixed>, context:array<string, mixed>} */
    private function expense(User $user, ReportFilter $filter): array
    {
        $from = $filter->from() ?? new \DateTimeImmutable('1970-01-01T00:00:00Z');
        $until = $filter->until() ?? now()->addDay();
        $allocations = $this->expenseAllocations->allocations($user, $from, $until);
        $categoryNames = Category::query()->whereIn('id', $allocations->pluck('category_id')->unique())->pluck('name', 'id');
        $rows = $allocations->groupBy('category_id')->map(fn (Collection $categoryAllocations, string $categoryId): array => [
            'category_id' => $categoryId, 'category' => $categoryNames->get($categoryId), 'expense_minor_units' => $categoryAllocations->sum('amount_minor_units'), 'allocation_count' => $categoryAllocations->count(),
        ])->values()->all();

        return $this->document('Expense Report', array_keys($rows[0] ?? $this->expenseRowShape()), $rows, ['expense_total_minor_units' => $allocations->sum('amount_minor_units')], $filter);
    }

    /** @return array{title:string, columns:list<string>, rows:list<array<string, scalar|null>>, summary:array<string, mixed>, context:array<string, mixed>} */
    private function income(User $user, ReportFilter $filter): array
    {
        $income = $this->filteredTransactions($user, $filter)->where('type', 'income')->with('merchant:id,display_name')->orderBy('occurred_at')->get();
        $rows = $income->map(fn (FinancialTransaction $transaction): array => [
            'transaction_id' => $transaction->getKey(), 'occurred_at' => $transaction->occurred_at->toISOString(), 'source' => $transaction->merchant->display_name ?? $transaction->raw_merchant_text ?? 'Unattributed',
            'base_amount_minor_units' => $transaction->base_amount_minor_units, 'base_currency_code' => $transaction->base_currency_code,
        ])->all();

        return $this->document('Income Report', array_keys($rows[0] ?? $this->incomeRowShape()), $rows, ['income_total_minor_units' => $income->sum('base_amount_minor_units'), 'concentration' => $this->incomeConcentration->calculate($user)], $filter);
    }

    /** @return array{title:string, columns:list<string>, rows:list<array<string, scalar|null>>, summary:array<string, mixed>, context:array<string, mixed>} */
    private function merchant(User $user, ReportFilter $filter): array
    {
        $transactions = $this->filteredTransactions($user, $filter)->whereIn('type', ['expense', 'fee', 'credit_card_purchase', 'refund'])->with('merchant:id,display_name')->get();
        $rows = $transactions->groupBy(fn (FinancialTransaction $transaction): string => $transaction->merchant_id ?? 'unattributed')->map(function (Collection $merchantTransactions, string $merchantId): array {
            $first = $merchantTransactions->first();

            return ['merchant_id' => $merchantId === 'unattributed' ? null : $merchantId, 'merchant' => $first?->merchant?->display_name ?: ($first?->raw_merchant_text ?: 'Unattributed'), 'spend_minor_units' => $merchantTransactions->sum(fn (FinancialTransaction $transaction): int => $transaction->type === 'refund' ? -((int) $transaction->base_amount_minor_units) : (int) $transaction->base_amount_minor_units), 'transaction_count' => $merchantTransactions->count()];
        })->values()->all();

        return $this->document('Merchant Report', array_keys($rows[0] ?? $this->merchantRowShape()), $rows, ['merchant_count' => count($rows)], $filter);
    }

    /** @return array{title:string, columns:list<string>, rows:list<array<string, scalar|null>>, summary:array<string, mixed>, context:array<string, mixed>} */
    private function itemPrices(User $user, ReportFilter $filter): array
    {
        $rows = LineItem::query()->where('line_items.user_id', $user->getKey())->whereNotNull('line_items.financial_transaction_id')->with(['canonicalItem:id,canonical_name', 'financialTransaction:id,occurred_at,state,merchant_id', 'financialTransaction.merchant:id,display_name'])->get()->filter(fn (LineItem $line): bool => $line->financialTransaction?->state === 'posted' && (! $filter->from() instanceof DateTimeInterface || $line->financialTransaction->occurred_at >= $filter->from()) && (! $filter->until() instanceof DateTimeInterface || $line->financialTransaction->occurred_at < $filter->until()))->sortBy(fn (LineItem $line): string => $line->financialTransaction->occurred_at->toISOString())->map(fn (LineItem $line): array => [
            'line_item_id' => $line->getKey(), 'occurred_at' => $line->financialTransaction->occurred_at->toISOString(), 'canonical_item' => $line->canonicalItem?->canonical_name,
            'merchant' => $line->financialTransaction->merchant?->display_name, 'quantity' => $line->quantity, 'unit_code' => $line->unit_code,
            'line_total_minor_units' => $line->line_total_minor_units, 'currency_code' => $line->currency_code,
        ])->values()->all();

        return $this->document('Item Price Report', array_keys($rows[0] ?? $this->itemPriceRowShape()), $rows, ['line_item_count' => count($rows)], $filter);
    }

    /** @return array{title:string, columns:list<string>, rows:list<array<string, scalar|null>>, summary:array<string, mixed>, context:array<string, mixed>} */
    private function multiCurrency(User $user, ReportFilter $filter): array
    {
        $transactions = $this->filteredTransactions($user, $filter)->whereColumn('original_currency_code', '!=', 'base_currency_code')->orderBy('occurred_at')->get();
        $rows = $transactions->map(fn (FinancialTransaction $transaction): array => [
            'transaction_id' => $transaction->getKey(), 'occurred_at' => $transaction->occurred_at->toISOString(), 'type' => $transaction->type,
            'original_amount_minor_units' => $transaction->original_amount_minor_units, 'original_currency_code' => $transaction->original_currency_code,
            'reference_rate' => $transaction->reference_rate, 'used_rate' => $transaction->used_rate, 'rate_date' => $transaction->rate_date?->toDateString(),
            'rate_source' => $transaction->rate_source, 'base_amount_minor_units' => $transaction->base_amount_minor_units, 'base_currency_code' => $transaction->base_currency_code,
            'rate_override_reason' => $transaction->rate_override_reason,
        ])->all();

        return $this->document('Multi-Currency Report', array_keys($rows[0] ?? $this->multiCurrencyRowShape()), $rows, ['foreign_transaction_count' => count($rows)], $filter);
    }

    /** @return Builder<FinancialTransaction> */
    private function filteredTransactions(User $user, ReportFilter $filter): Builder
    {
        return FinancialTransaction::query()->ownedBy($user)->where('state', 'posted')
            ->when($filter->from(), fn (Builder $query, DateTimeInterface $from): Builder => $query->where('occurred_at', '>=', $from))
            ->when($filter->until(), fn (Builder $query, DateTimeInterface $until): Builder => $query->where('occurred_at', '<', $until))
            ->when($filter->filters['financial_account_id'] ?? null, fn (Builder $query, string $accountId): Builder => $query->where('financial_account_id', $accountId))
            ->when($filter->filters['category_id'] ?? null, fn (Builder $query, string $categoryId): Builder => $query->where('category_id', $categoryId))
            ->when($filter->filters['merchant_id'] ?? null, fn (Builder $query, string $merchantId): Builder => $query->where('merchant_id', $merchantId))
            ->when($filter->filters['transaction_type'] ?? null, fn (Builder $query, string $type): Builder => $query->where('type', $type))
            ->when($filter->filters['currency_code'] ?? null, fn (Builder $query, string $currencyCode): Builder => $query->where('original_currency_code', $currencyCode));
    }

    /** @param Collection<int, JournalLine> $lines */
    private function accountBalance(Collection $lines, string $accountingType): int
    {
        return $lines->sum(fn (JournalLine $line): int => $accountingType === 'liability' ? $line->credit_minor_units - $line->debit_minor_units : $line->debit_minor_units - $line->credit_minor_units);
    }

    /**
     * @param  list<string>  $columns
     * @param  array<int, array<string, scalar|null>>  $rows
     * @param  array<string, mixed>  $summary
     * @return array{title:string, columns:list<string>, rows:list<array<string, scalar|null>>, summary:array<string, mixed>, context:array<string, mixed>}
     */
    private function document(string $title, array $columns, array $rows, array $summary, ReportFilter $filter): array
    {
        return ['title' => $title, 'columns' => $columns, 'rows' => array_values($rows), 'summary' => $summary, 'context' => $filter->metadata()];
    }

    /** @return array<string, null> */
    private function transactionRowShape(): array
    {
        return array_fill_keys(['id', 'occurred_at', 'type', 'account', 'counterparty_account', 'category', 'merchant', 'original_amount_minor_units', 'original_currency_code', 'base_amount_minor_units', 'base_currency_code', 'splits', 'state'], null);
    }

    /** @return array<string, null> */
    private function accountRowShape(): array
    {
        return array_fill_keys(['account_id', 'account', 'currency_code', 'opening_balance_minor_units', 'period_change_minor_units', 'closing_balance_minor_units', 'journal_line_count'], null);
    }

    /** @return array<string, null> */
    private function budgetRowShape(): array
    {
        return array_fill_keys(['budget_period_id', 'period', 'category', 'currency_code', 'base_limit_minor_units', 'borrowing_deduction_minor_units', 'positive_rollover_minor_units', 'negative_carry_minor_units', 'reallocation_in_minor_units', 'reallocation_out_minor_units', 'effective_limit_minor_units', 'actual_spent_minor_units', 'remaining_minor_units', 'adjustments'], null);
    }

    /** @return array<string, null> */
    private function expenseRowShape(): array
    {
        return array_fill_keys(['category_id', 'category', 'expense_minor_units', 'allocation_count'], null);
    }

    /** @return array<string, null> */
    private function incomeRowShape(): array
    {
        return array_fill_keys(['transaction_id', 'occurred_at', 'source', 'base_amount_minor_units', 'base_currency_code'], null);
    }

    /** @return array<string, null> */
    private function merchantRowShape(): array
    {
        return array_fill_keys(['merchant_id', 'merchant', 'spend_minor_units', 'transaction_count'], null);
    }

    /** @return array<string, null> */
    private function itemPriceRowShape(): array
    {
        return array_fill_keys(['line_item_id', 'occurred_at', 'canonical_item', 'merchant', 'quantity', 'unit_code', 'line_total_minor_units', 'currency_code'], null);
    }

    /** @return array<string, null> */
    private function multiCurrencyRowShape(): array
    {
        return array_fill_keys(['transaction_id', 'occurred_at', 'type', 'original_amount_minor_units', 'original_currency_code', 'reference_rate', 'used_rate', 'rate_date', 'rate_source', 'base_amount_minor_units', 'base_currency_code', 'rate_override_reason'], null);
    }
}
