<?php

namespace Database\Factories;

use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JournalLine> */
class JournalLineFactory extends Factory
{
    public function definition(): array
    {
        return ['journal_entry_id' => JournalEntry::factory(), 'user_id' => User::factory(), 'ledger_code' => 'expense.fixture', 'debit_minor_units' => 1000, 'credit_minor_units' => 0, 'currency_code' => 'ETB', 'base_debit_minor_units' => 1000, 'base_credit_minor_units' => 0, 'base_currency_code' => 'ETB', 'sequence' => 0];
    }
}
