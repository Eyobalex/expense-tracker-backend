<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\SyncOperation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<SyncOperation> */
class SyncOperationFactory extends Factory
{
    public function definition(): array
    {
        $user = User::factory();

        return [
            'user_id' => $user, 'device_id' => Device::factory()->for($user), 'client_operation_id' => (string) Str::uuid(),
            'entity' => 'account', 'action' => 'create', 'payload_hash' => hash('sha256', fake()->uuid()), 'status' => 'succeeded',
            'response_payload' => [], 'completed_at' => now(),
        ];
    }
}
