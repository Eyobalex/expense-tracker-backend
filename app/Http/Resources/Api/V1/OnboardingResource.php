<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class OnboardingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'base_currency_code' => $user->base_currency_code,
            'timezone' => $user->timezone,
            'budget_timezone' => $user->budget_timezone,
            'notification_preferences' => $user->notification_preferences,
            'onboarding_completed' => $user->onboarding_completed,
            'version' => $user->version,
        ];
    }
}
