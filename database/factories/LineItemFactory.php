<?php

namespace Database\Factories;

use App\Models\LineItem;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LineItem> */
class LineItemFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'receipt_id' => Receipt::factory(), 'raw_description' => fake()->words(3, true), 'sequence' => 0];
    }

    public function forReceipt(Receipt $receipt): static
    {
        return $this->state(fn (): array => ['user_id' => $receipt->user_id, 'receipt_id' => $receipt->id]);
    }
}
