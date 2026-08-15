<?php

namespace App\Application\Insights;

use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PostedExpenseAllocationRepository
{
    /**
     * @return Collection<int, array{category_id: string, transaction_id: string, transaction_type: string, occurred_at: string, effective_occurred_at: string, amount_minor_units: int}>
     */
    public function allocations(User $user, DateTimeInterface $from, DateTimeInterface $until): Collection
    {
        return DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('financial_transactions', 'financial_transactions.id', '=', 'journal_entries.financial_transaction_id')
            ->join('ledger_account_mappings', function ($join): void {
                $join->on('ledger_account_mappings.user_id', '=', 'journal_lines.user_id')
                    ->on('ledger_account_mappings.ledger_code', '=', 'journal_lines.ledger_code')
                    ->where('ledger_account_mappings.entity_type', '=', 'category')
                    ->where('ledger_account_mappings.mapping_key', '=', 'allocation');
            })
            ->leftJoin('financial_transactions as original_transactions', 'original_transactions.id', '=', 'financial_transactions.related_transaction_id')
            ->where('financial_transactions.user_id', $user->getKey())
            ->where('financial_transactions.state', 'posted')
            ->whereIn('financial_transactions.type', ['expense', 'fee', 'credit_card_purchase', 'refund'])
            ->whereRaw("CASE WHEN financial_transactions.type = 'refund' THEN original_transactions.occurred_at ELSE financial_transactions.occurred_at END >= ?", [$from])
            ->whereRaw("CASE WHEN financial_transactions.type = 'refund' THEN original_transactions.occurred_at ELSE financial_transactions.occurred_at END < ?", [$until])
            ->select([
                'ledger_account_mappings.entity_id as category_id',
                'financial_transactions.id as transaction_id',
                'financial_transactions.type as transaction_type',
                'financial_transactions.occurred_at',
                DB::raw("CASE WHEN financial_transactions.type = 'refund' THEN original_transactions.occurred_at ELSE financial_transactions.occurred_at END as effective_occurred_at"),
                DB::raw('(journal_lines.base_debit_minor_units - journal_lines.base_credit_minor_units) as amount_minor_units'),
            ])
            ->orderBy('effective_occurred_at')
            ->orderBy('financial_transactions.id')
            ->get()
            ->map(function (object $allocation): array {
                return [
                    'category_id' => (string) $allocation->category_id,
                    'transaction_id' => (string) $allocation->transaction_id,
                    'transaction_type' => (string) $allocation->transaction_type,
                    'occurred_at' => (string) $allocation->occurred_at,
                    'effective_occurred_at' => (string) $allocation->effective_occurred_at,
                    'amount_minor_units' => (int) $allocation->amount_minor_units,
                ];
            });
    }
}
