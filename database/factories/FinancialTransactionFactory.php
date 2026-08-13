<?php

namespace Database\Factories;

use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FinancialTransaction> */
class FinancialTransactionFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'financial_account_id' => FinancialAccount::factory(), 'type' => 'expense', 'state' => 'draft', 'source' => 'manual', 'occurred_at' => now(), 'occurred_timezone' => 'Africa/Addis_Ababa', 'original_amount_minor_units' => 1000, 'original_currency_code' => 'ETB', 'version' => 1];
    }
}
