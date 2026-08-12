<?php

namespace Database\Factories;

use App\Domain\Budgeting\Enums\BudgetAdjustmentType;
use App\Models\BudgetAdjustment;
use App\Models\BudgetPeriod;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BudgetAdjustment> */
class BudgetAdjustmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'budget_period_id' => BudgetPeriod::factory(),
            'type' => BudgetAdjustmentType::Correction->value,
            'amount_minor_units' => 100,
            'currency_code' => 'ETB',
            'reason' => 'Test adjustment.',
        ];
    }
}
