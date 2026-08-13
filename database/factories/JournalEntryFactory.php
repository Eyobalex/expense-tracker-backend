<?php

namespace Database\Factories;

use App\Models\FinancialTransaction;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JournalEntry> */
class JournalEntryFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'financial_transaction_id' => FinancialTransaction::factory(), 'type' => 'normal', 'functional_currency_code' => 'ETB', 'posted_at' => now()];
    }
}
