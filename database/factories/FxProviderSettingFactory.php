<?php

namespace Database\Factories;

use App\Models\FxProviderSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FxProviderSetting> */
class FxProviderSettingFactory extends Factory
{
    protected $model = FxProviderSetting::class;

    public function definition(): array
    {
        return [
            'provider' => 'open_exchange_rates',
            'base_currency_code' => 'USD',
            'quote_currency_code' => 'ETB',
            'refresh_time' => '00:30',
            'monthly_quota' => 1000,
            'alert_after_hours' => 24,
            'maximum_staleness_hours' => 48,
            'fallback_policy' => 'latest_valid_within_maximum_staleness',
            'is_enabled' => true,
        ];
    }
}
