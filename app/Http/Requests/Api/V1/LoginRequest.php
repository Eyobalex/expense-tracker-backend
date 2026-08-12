<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'client_device_id' => ['required', 'uuid'],
            'platform' => ['required', 'in:android'],
            'app_version' => ['nullable', 'string', 'max:64'],
        ];
    }
}
