<?php

namespace Database\Factories;

use App\Models\NotificationDelivery;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NotificationDelivery>
 */
class NotificationDeliveryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_notification_id' => UserNotification::factory(),
            'channel' => 'in_app',
            'status' => 'queued',
            'idempotency_key' => hash('sha256', (string) Str::uuid()),
        ];
    }
}
