<?php

namespace App\Infrastructure\Currency;

use App\Application\Currency\FxSettings;
use App\Domain\Currency\Contracts\ExchangeRateProvider;
use App\Domain\Currency\ExchangeRateQuote;
use App\Domain\Currency\ValueObjects\CurrencyCode;
use App\Domain\Currency\ValueObjects\ExchangeRate;
use App\Domain\Shared\Exceptions\DomainErrorCode;
use App\Domain\Shared\Exceptions\DomainException;
use DateTimeImmutable;
use Illuminate\Support\Facades\Http;

final class OpenExchangeRatesProvider implements ExchangeRateProvider
{
    public function __construct(private readonly FxSettings $settings) {}

    public function fetch(CurrencyCode $baseCurrency, CurrencyCode $quoteCurrency, DateTimeImmutable $rateDate): ExchangeRateQuote
    {
        if ($baseCurrency->toString() !== $this->settings->baseCurrency() || $quoteCurrency->toString() !== $this->settings->quoteCurrency()) {
            throw DomainException::for(DomainErrorCode::InvalidRateLock, 'The configured FX provider supports only the approved USD to ETB source pair.');
        }

        $appId = (string) config('services.open_exchange_rates.app_id');
        if (trim($appId) === '') {
            throw DomainException::for(DomainErrorCode::InvalidRateLock, 'The Open Exchange Rates app ID is not configured.');
        }

        $response = Http::baseUrl((string) config('fx.http.base_url'))
            ->connectTimeout((int) config('fx.http.connect_timeout'))
            ->timeout((int) config('fx.http.timeout'))
            ->retry(4, fn (int $attempt): int => 1000 * (2 ** ($attempt - 1)))
            ->acceptJson()
            ->get('/historical/'.$rateDate->format('Y-m-d').'.json', [
                'app_id' => $appId,
                'symbols' => $quoteCurrency->toString(),
            ]);
        $response->throw();
        $body = $response->json();
        $rate = data_get($body, 'rates.'.$quoteCurrency->toString());
        if (! is_int($rate) && ! is_float($rate) && ! is_string($rate)) {
            throw DomainException::for(DomainErrorCode::InvalidExchangeRate, 'The Open Exchange Rates response does not contain the configured ETB rate.');
        }

        $timestamp = data_get($body, 'timestamp');

        return new ExchangeRateQuote(
            $baseCurrency,
            $quoteCurrency,
            ExchangeRate::fromDecimal((string) $rate),
            $rateDate,
            'open_exchange_rates',
            $timestamp === null ? new DateTimeImmutable : (new DateTimeImmutable)->setTimestamp((int) $timestamp),
        );
    }
}
