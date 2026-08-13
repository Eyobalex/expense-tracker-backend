<?php

namespace Database\Factories;

use App\Models\BudgetPeriod;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BudgetPeriod> */
class BudgetPeriodFactory extends Factory
{
    public function definition(): array
    {
        $start = now('UTC')->startOfMonth();

        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'period_year' => (int) $start->year,
            'period_month' => (int) $start->month,
            'budget_timezone' => 'UTC',
            'period_start_at' => $start,
            'period_end_at' => $start->addMonth(),
            'status' => 'open',
            'currency_code' => 'ETB',
            'base_limit_minor_units' => 10000,
            'initialized_at' => now(),
        ];
    }
}
