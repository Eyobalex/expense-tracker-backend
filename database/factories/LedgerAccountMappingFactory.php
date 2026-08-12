<?php

namespace Database\Factories;

use App\Models\LedgerAccountMapping;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LedgerAccountMapping> */
class LedgerAccountMappingFactory extends Factory
{
    public function definition(): array
    {
        $code = fake()->unique()->slug();

        return [
            'user_id' => User::factory(),
            'entity_type' => 'system',
            'mapping_key' => $code,
            'ledger_code' => $code,
        ];
    }
}
