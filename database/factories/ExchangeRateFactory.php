<?php

namespace Database\Factories;

use App\Models\ExchangeRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeRate>
 */
class ExchangeRateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'base_currency_code' => 'USD',
            'quote_currency_code' => 'ETB',
            'rate_date' => now()->toDateString(),
            'rate' => '55.000000000000000000',
            'provider' => 'open_exchange_rates',
            'provider_published_at' => now(),
            'retrieved_at' => now(),
            'status' => 'fresh',
        ];
    }
}
