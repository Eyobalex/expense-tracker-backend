<?php

namespace App\Application\Currency;

use App\Models\FxProviderSetting;

final class FxSettings
{
    public function provider(): string
    {
        $setting = $this->current();

        return $setting instanceof FxProviderSetting ? $setting->provider : (string) config('fx.provider');
    }

    public function baseCurrency(): string
    {
        $setting = $this->current();

        return $setting instanceof FxProviderSetting ? $setting->base_currency_code : (string) config('fx.base_currency');
    }

    public function quoteCurrency(): string
    {
        $setting = $this->current();

        return $setting instanceof FxProviderSetting ? $setting->quote_currency_code : (string) config('fx.quote_currency');
    }

    public function alertAfterHours(): int
    {
        $setting = $this->current();

        return $setting instanceof FxProviderSetting ? $setting->alert_after_hours : (int) config('fx.alert_after_hours');
    }

    public function maximumStalenessHours(): int
    {
        $setting = $this->current();

        return $setting instanceof FxProviderSetting ? $setting->maximum_staleness_hours : (int) config('fx.maximum_staleness_hours');
    }

    private function current(): ?FxProviderSetting
    {
        return FxProviderSetting::query()->where('is_enabled', true)->orderBy('provider')->first();
    }
}
