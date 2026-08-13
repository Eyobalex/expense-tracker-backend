<?php

namespace Database\Factories;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AuditEvent> */
class AuditEventFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'event_name' => 'transaction.posted', 'aggregate_type' => 'financial_transaction', 'aggregate_id' => (string) fake()->uuid(), 'summary' => []];
    }
}
