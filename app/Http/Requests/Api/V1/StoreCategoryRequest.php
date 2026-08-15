<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
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
            'kind' => ['required', 'in:expense,income'],
            'parent_id' => ['nullable', 'uuid'],
            'budget_enabled' => ['sometimes', 'boolean'],
            'forecast_behavior' => ['sometimes', 'in:fixed,periodic,variable'],
            'base_limit_minor_units' => ['nullable', 'integer', 'min:0'],
            'budget_currency_code' => ['nullable', 'regex:/^[A-Z]{3}$/'],
            'rollover_enabled' => ['sometimes', 'boolean'],
            'overspend_carry_enabled' => ['sometimes', 'boolean'],
            'borrowing_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
