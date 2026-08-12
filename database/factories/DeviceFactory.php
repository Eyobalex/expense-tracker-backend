<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'client_device_id' => (string) Str::uuid(),
            'platform' => 'android',
            'app_version' => '1.0.0',
            'last_seen_at' => now(),
        ];
    }
}
