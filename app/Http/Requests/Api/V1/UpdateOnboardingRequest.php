<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'base_currency_code' => ['required', 'regex:/^[A-Z]{3}$/'],
            'timezone' => ['required', 'timezone'],
            'budget_timezone' => ['required', 'timezone'],
            'notification_preferences' => ['sometimes', 'array'],
            'seed_starter_data' => ['sometimes', 'boolean'],
        ];
    }
}
