<?php

namespace App\Application\Currency;

use App\Domain\Currency\Contracts\ExchangeRateProvider;
use App\Domain\Currency\ValueObjects\CurrencyCode;
use App\Models\ExchangeRate;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;

final readonly class ExchangeRateRefreshService
{
    public function __construct(private ExchangeRateProvider $provider, private FxSettings $settings) {}

    public function refresh(DateTimeImmutable $rateDate): ExchangeRate
    {
        $base = CurrencyCode::fromString($this->settings->baseCurrency());
        $quote = CurrencyCode::fromString($this->settings->quoteCurrency());
        $providerQuote = $this->provider->fetch($base, $quote, $rateDate);
        $record = ExchangeRate::query()->updateOrCreate(
            [
                'base_currency_code' => $base->toString(),
                'quote_currency_code' => $quote->toString(),
                'rate_date' => $providerQuote->rateDate->format('Y-m-d'),
                'provider' => $this->settings->provider(),
            ],
            [
                'rate' => $providerQuote->rate->decimal(),
                'provider_published_at' => $providerQuote->retrievedAt,
                'retrieved_at' => now(),
                'status' => 'fresh',
                'provider_metadata' => ['source' => $providerQuote->source],
            ],
        );
        Log::info('fx.rate_refreshed', [
            'provider' => $record->provider,
            'exchange_rate_id' => $record->getKey(),
            'base_currency_code' => $record->base_currency_code,
            'quote_currency_code' => $record->quote_currency_code,
            'rate_date' => $record->rate_date->toDateString(),
        ]);

        return $record;
    }
}
