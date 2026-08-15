<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SyncPushRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'operation_id' => ['required', 'uuid'],
            'device_id' => ['required', 'uuid'],
            'entity' => ['required', 'in:account,category,merchant,item,transaction'],
            'action' => ['required', 'in:create,update,delete,post,archive,restore'],
            'local_id' => ['nullable', 'uuid'],
            'server_id' => ['nullable', 'uuid'],
            'expected_version' => ['nullable', 'integer', 'min:1'],
            'payload' => ['present', 'array'],
            'client_occurred_at' => ['nullable', 'date'],
        ];
    }
}
