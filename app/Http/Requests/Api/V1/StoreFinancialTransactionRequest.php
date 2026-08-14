<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFinancialTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'financial_account_id' => ['required', 'uuid'], 'counterparty_account_id' => ['nullable', 'uuid', 'different:financial_account_id'],
            'category_id' => ['nullable', 'uuid'], 'merchant_id' => ['nullable', 'uuid'], 'raw_merchant_text' => ['nullable', 'string', 'max:500'], 'related_transaction_id' => ['nullable', 'uuid'],
            'type' => ['required', 'in:expense,income,transfer,credit_card_purchase,credit_card_repayment,refund,fee,opening_balance,adjustment'],
            'state' => ['prohibited'], 'source' => ['prohibited'], 'adjustment_subtype' => ['required_if:type,adjustment', 'in:balance_correction'],
            'adjustment_direction' => ['required_if:type,adjustment', 'in:debit,credit'], 'reason' => ['required_if:type,adjustment', 'string', 'max:500'],
            'occurred_at' => ['required', 'date'], 'occurred_timezone' => ['required', 'timezone'],
            'original_amount_minor_units' => ['required', 'integer', 'min:1'], 'original_currency_code' => ['required', 'regex:/^[A-Z]{3}$/'],
            'counterparty_amount_minor_units' => ['nullable', 'integer', 'min:1'], 'counterparty_currency_code' => ['nullable', 'regex:/^[A-Z]{3}$/'],
            'reference_rate' => ['nullable', 'regex:/^[0-9]+(?:\.[0-9]{1,18})?$/'], 'used_rate' => ['nullable', 'regex:/^[0-9]+(?:\.[0-9]{1,18})?$/'],
            'rate_date' => ['nullable', 'date'], 'rate_source' => ['nullable', 'string', 'max:64'], 'rate_override_reason' => ['nullable', 'string', 'max:500'],
            'rounding_mode' => ['nullable', 'in:HALF_UP,HALF_EVEN,DOWN,UP,FLOOR,CEILING'], 'description' => ['nullable', 'string', 'max:500'], 'reference_number' => ['nullable', 'string', 'max:128'],
            'splits' => ['nullable', 'array'], 'splits.*.category_id' => ['required', 'uuid'], 'splits.*.canonical_item_id' => ['nullable', 'uuid'], 'splits.*.raw_item_text' => ['nullable', 'string', 'max:500'], 'splits.*.amount_minor_units' => ['required', 'integer', 'min:1'],
            'splits.*.currency_code' => ['required', 'regex:/^[A-Z]{3}$/'], 'splits.*.classification' => ['nullable', 'in:category,tax,fee,discount,rounding'], 'splits.*.description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
