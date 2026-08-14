<?php

namespace Database\Seeders;

use App\Models\FxProviderSetting;
use Illuminate\Database\Seeder;

class FxProviderSettingSeeder extends Seeder
{
    public function run(): void
    {
        FxProviderSetting::query()->updateOrCreate(
            [
                'provider' => (string) config('fx.provider'),
                'base_currency_code' => (string) config('fx.base_currency'),
                'quote_currency_code' => (string) config('fx.quote_currency'),
            ],
            [
                'refresh_time' => (string) config('fx.refresh_time'),
                'monthly_quota' => (int) config('fx.monthly_quota'),
                'alert_after_hours' => (int) config('fx.alert_after_hours'),
                'maximum_staleness_hours' => (int) config('fx.maximum_staleness_hours'),
                'fallback_policy' => 'latest_valid_within_maximum_staleness',
                'is_enabled' => true,
            ],
        );
    }
}
