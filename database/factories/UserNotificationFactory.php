<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UserNotification>
 */
class UserNotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'budget_threshold',
            'title' => 'Budget threshold reached',
            'body' => 'A budget category reached its configured threshold.',
            'payload' => ['schema_version' => 1],
            'in_app_enabled' => true,
            'deduplication_key' => 'test:'.Str::uuid(),
        ];
    }
}
