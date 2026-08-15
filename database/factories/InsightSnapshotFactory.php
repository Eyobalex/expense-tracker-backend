<?php

namespace Database\Factories;

use App\Models\InsightSnapshot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InsightSnapshot>
 */
class InsightSnapshotFactory extends Factory
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
            'formula_name' => 'safe_to_spend',
            'formula_version' => 1,
            'timezone' => 'Africa/Addis_Ababa',
            'base_currency_code' => 'ETB',
            'inputs' => ['budget_period' => '2026-08'],
            'result' => ['value_minor_units' => 100],
            'calculated_at' => now(),
        ];
    }
}
