<?php

namespace App\Application\Budgeting;

use App\Models\BudgetPeriod;
use App\Models\LedgerAccountMapping;
use Illuminate\Support\Facades\DB;

final class BudgetActualSpentCalculator
{
    public function calculate(BudgetPeriod $period): int
    {
        $ledgerCode = LedgerAccountMapping::query()
            ->where('user_id', $period->user_id)
            ->where('entity_type', 'category')
            ->where('entity_id', $period->category_id)
            ->where('mapping_key', 'allocation')
            ->value('ledger_code');

        if (! is_string($ledgerCode)) {
            return 0;
        }

        $spent = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('financial_transactions', 'financial_transactions.id', '=', 'journal_entries.financial_transaction_id')
            ->leftJoin('financial_transactions as original_transactions', 'original_transactions.id', '=', 'financial_transactions.related_transaction_id')
            ->where('journal_lines.user_id', $period->user_id)
            ->where('journal_lines.ledger_code', $ledgerCode)
            ->where('financial_transactions.state', 'posted')
            ->where(function ($query) use ($period): void {
                $query->where(function ($ordinaryTransactions) use ($period): void {
                    $ordinaryTransactions
                        ->where('financial_transactions.type', '!=', 'refund')
                        ->where('financial_transactions.occurred_at', '>=', $period->period_start_at)
                        ->where('financial_transactions.occurred_at', '<', $period->period_end_at);
                })->orWhere(function ($refunds) use ($period): void {
                    $refunds
                        ->where('financial_transactions.type', 'refund')
                        ->where('original_transactions.occurred_at', '>=', $period->period_start_at)
                        ->where('original_transactions.occurred_at', '<', $period->period_end_at);
                });
            })
            ->selectRaw('COALESCE(SUM(journal_lines.base_debit_minor_units - journal_lines.base_credit_minor_units), 0) as spent')
            ->value('spent');

        return (int) $spent;
    }
}
