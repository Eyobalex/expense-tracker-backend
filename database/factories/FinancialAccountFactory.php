<?php

namespace Database\Factories;

use App\Models\FinancialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FinancialAccount> */
class FinancialAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->word(),
            'type' => 'cash',
            'accounting_type' => 'asset',
            'currency_code' => 'ETB',
            'opening_balance_configured' => false,
            'version' => 1,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['archived_at' => now()]);
    }
}
