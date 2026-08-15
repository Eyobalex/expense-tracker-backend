<?php

namespace App\Infrastructure\Currency;

use App\Application\Currency\FxSettings;
use App\Domain\Currency\Contracts\HistoricalExchangeRateLookup;
use App\Domain\Currency\ExchangeRateQuote;
use App\Domain\Currency\RateLookupResult;
use App\Domain\Currency\ValueObjects\CurrencyCode;
use App\Domain\Currency\ValueObjects\ExchangeRate;
use App\Models\ExchangeRate as ExchangeRateModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;

final class DatabaseHistoricalExchangeRateLookup implements HistoricalExchangeRateLookup
{
    public function __construct(private readonly FxSettings $settings) {}

    public function find(CurrencyCode $baseCurrency, CurrencyCode $quoteCurrency, DateTimeImmutable $rateDate): RateLookupResult
    {
        $providerBase = $this->settings->baseCurrency();
        $providerQuote = $this->settings->quoteCurrency();
        $direct = $baseCurrency->toString() === $providerBase && $quoteCurrency->toString() === $providerQuote;
        $inverse = $baseCurrency->toString() === $providerQuote && $quoteCurrency->toString() === $providerBase;
        if (! $direct && ! $inverse) {
            return RateLookupResult::unavailable();
        }

        $record = ExchangeRateModel::query()
            ->where('base_currency_code', $providerBase)
            ->where('quote_currency_code', $providerQuote)
            ->where('provider', $this->settings->provider())
            ->whereDate('rate_date', '<=', $rateDate->format('Y-m-d'))
            ->orderByDesc('rate_date')
            ->first();
        if (! $record instanceof ExchangeRateModel) {
            return RateLookupResult::unavailable();
        }

        $rate = ExchangeRate::fromDecimal($record->rate);
        $quote = new ExchangeRateQuote(
            $baseCurrency,
            $quoteCurrency,
            $inverse ? $rate->reciprocal() : $rate,
            $record->rate_date->toDateTimeImmutable(),
            $inverse ? $record->provider.':reciprocal' : $record->provider,
            $record->retrieved_at->toDateTimeImmutable(),
        );
        $now = now();
        if ($record->retrieved_at->lessThan($now->copy()->subHours($this->settings->maximumStalenessHours()))) {
            return RateLookupResult::stale($quote);
        }

        if ($record->retrieved_at->lessThan($now->copy()->subHours($this->settings->alertAfterHours()))) {
            Log::warning('fx.rate_freshness_alert', [
                'exchange_rate_id' => $record->getKey(),
                'base_currency_code' => $record->base_currency_code,
                'quote_currency_code' => $record->quote_currency_code,
                'rate_date' => $record->rate_date->toDateString(),
                'age_hours' => $record->retrieved_at->diffInHours($now),
            ]);
        }

        return $record->rate_date->toDateString() === $rateDate->format('Y-m-d')
            ? RateLookupResult::exact($quote)
            : RateLookupResult::latestValid($quote);
    }
}
