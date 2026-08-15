<?php

namespace Database\Factories;

use App\Models\SyncTombstone;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<SyncTombstone> */
class SyncTombstoneFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(), 'resource_type' => 'transaction', 'resource_id' => (string) Str::uuid(),
            'version' => 2, 'deleted_at' => now(),
        ];
    }
}
