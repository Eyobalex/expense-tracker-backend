<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->word(),
            'kind' => 'expense',
            'is_active' => true,
            'is_system' => false,
            'budget_enabled' => false,
            'rollover_enabled' => false,
            'overspend_carry_enabled' => false,
            'borrowing_enabled' => false,
            'version' => 1,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['is_active' => false, 'archived_at' => now()]);
    }
}
