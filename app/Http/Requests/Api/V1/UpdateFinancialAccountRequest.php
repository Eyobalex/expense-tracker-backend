<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFinancialAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'type' => ['sometimes', 'in:cash,bank,mobile_wallet,savings,credit_card,loan,investment,other'],
            'currency_code' => ['sometimes', 'regex:/^[A-Z]{3}$/'],
            'opening_balance_configured' => ['sometimes', 'boolean'],
        ];
    }
}
