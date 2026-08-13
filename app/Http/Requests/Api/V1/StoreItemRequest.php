<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['canonical_name' => ['required', 'string', 'max:180'], 'unit_code' => ['nullable', 'string', 'max:32'], 'pack_size_value' => ['nullable', 'decimal:0,6', 'gt:0', 'required_with:pack_size_unit'], 'pack_size_unit' => ['nullable', 'string', 'max:32', 'required_with:pack_size_value']];
    }
}
