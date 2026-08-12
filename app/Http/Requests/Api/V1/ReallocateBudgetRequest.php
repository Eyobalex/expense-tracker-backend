<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReallocateBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'month' => ['required', 'date_format:Y-m'],
            'target_category_id' => ['required', 'uuid', 'different:category'],
            'amount_minor_units' => ['required', 'integer', 'min:1'],
        ];
    }
}
