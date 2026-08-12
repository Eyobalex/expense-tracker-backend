<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFinancialAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:cash,bank,mobile_wallet,savings,credit_card,loan,investment,other'],
            'currency_code' => ['required', 'regex:/^[A-Z]{3}$/'],
            'opening_balance_configured' => ['sometimes', 'boolean'],
        ];
    }
}
