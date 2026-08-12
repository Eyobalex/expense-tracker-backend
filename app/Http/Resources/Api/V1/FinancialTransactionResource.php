<?php

namespace App\Http\Resources\Api\V1;

use App\Models\FinancialTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FinancialTransaction */
class FinancialTransactionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var FinancialTransaction $transaction */
        $transaction = $this->resource;

        return [
            'id' => $transaction->id, 'type' => $transaction->type, 'state' => $transaction->state, 'source' => $transaction->source,
            'financial_account_id' => $transaction->financial_account_id, 'counterparty_account_id' => $transaction->counterparty_account_id,
            'category_id' => $transaction->category_id, 'related_transaction_id' => $transaction->related_transaction_id,
            'original_amount_minor_units' => $transaction->original_amount_minor_units, 'original_currency_code' => $transaction->original_currency_code,
            'counterparty_amount_minor_units' => $transaction->counterparty_amount_minor_units, 'counterparty_currency_code' => $transaction->counterparty_currency_code,
            'base_amount_minor_units' => $transaction->base_amount_minor_units, 'base_currency_code' => $transaction->base_currency_code,
            'reference_rate' => $transaction->reference_rate, 'used_rate' => $transaction->used_rate, 'rate_date' => $transaction->rate_date?->toDateString(),
            'rate_source' => $transaction->rate_source, 'rounding_mode' => $transaction->rounding_mode, 'adjustment_subtype' => $transaction->adjustment_subtype,
            'reason' => $transaction->reason, 'description' => $transaction->description, 'occurred_at' => $transaction->occurred_at->toISOString(),
            'occurred_timezone' => $transaction->occurred_timezone, 'posted_at' => $transaction->posted_at?->toISOString(), 'reversed_at' => $transaction->reversed_at?->toISOString(),
            'journal_entry_id' => $transaction->journal_entry_id, 'reversal_of_id' => $transaction->reversal_of_id, 'correction_of_id' => $transaction->correction_of_id,
            'version' => $transaction->version,
            'splits' => $transaction->relationLoaded('splits') ? $transaction->splits->map(fn ($split): array => ['id' => $split->id, 'category_id' => $split->category_id, 'amount_minor_units' => $split->amount_minor_units, 'currency_code' => $split->currency_code, 'classification' => $split->classification, 'description' => $split->description, 'sequence' => $split->sequence])->all() : [],
            'created_at' => $transaction->created_at?->toISOString(), 'updated_at' => $transaction->updated_at?->toISOString(),
        ];
    }
}
