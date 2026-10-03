<?php

namespace Database\Factories;

use App\Models\AdminAuditEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminAuditEvent>
 */
class AdminAuditEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'action' => 'staff.test',
            'outcome' => 'allowed',
            'context' => [],
            'created_at' => now(),
        ];
    }
}
