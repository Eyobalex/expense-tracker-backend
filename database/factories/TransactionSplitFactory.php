<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\FinancialTransaction;
use App\Models\TransactionSplit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TransactionSplit> */
class TransactionSplitFactory extends Factory
{
    public function definition(): array
    {
        return ['financial_transaction_id' => FinancialTransaction::factory(), 'category_id' => Category::factory(), 'amount_minor_units' => 1000, 'currency_code' => 'ETB', 'classification' => 'category', 'sequence' => 0];
    }
}
