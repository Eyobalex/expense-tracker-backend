<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
        $rules = ['enabled' => ['sometimes', 'boolean'], 'channels' => ['sometimes', 'array'], 'types' => ['sometimes', 'array'], 'budget_thresholds' => ['sometimes', 'array', 'min:1', 'max:4'], 'budget_thresholds.*' => ['integer', 'min:1', 'max:100', 'distinct']];
        foreach (NotificationChannel::cases() as $channel) {
            $rules['channels.'.$channel->value] = ['sometimes', 'boolean'];
        }
        foreach (NotificationType::cases() as $type) {
            $rules['types.'.$type->value] = ['sometimes', 'boolean'];
        }

        return $rules;
    }
}
