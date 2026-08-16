<?php

namespace Database\Factories;

use App\Models\ReportJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportJob>
 */
class ReportJobFactory extends Factory
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
            'type' => 'transaction',
            'format' => 'csv',
            'filters' => [],
            'base_currency_code' => 'ETB',
            'timezone' => 'Africa/Addis_Ababa',
            'status' => 'queued',
            'progress' => 0,
            'expires_at' => now()->addDays(7),
        ];
    }
}
