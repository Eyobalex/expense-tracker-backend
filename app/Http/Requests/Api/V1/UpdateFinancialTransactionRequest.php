<?php

namespace App\Http\Requests\Api\V1;

class UpdateFinancialTransactionRequest extends StoreFinancialTransactionRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        foreach ($rules as $key => $rule) {
            if (in_array($key, ['financial_account_id', 'type', 'occurred_at', 'occurred_timezone', 'original_amount_minor_units', 'original_currency_code'], true)) {
                $rules[$key] = array_merge(['sometimes'], array_values(array_filter((array) $rule, fn (mixed $value): bool => $value !== 'required')));
            }
        }

        return $rules;
    }
}
