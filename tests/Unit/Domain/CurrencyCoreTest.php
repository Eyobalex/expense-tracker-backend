<?php

use App\Domain\Currency\Enums\RateLookupStatus;
use App\Domain\Currency\Enums\RoundingMode;
use App\Domain\Currency\ExchangeRateQuote;
use App\Domain\Currency\LockedExchangeRate;
use App\Domain\Currency\RateLookupResult;
use App\Domain\Currency\ValueObjects\CurrencyCode;
use App\Domain\Currency\ValueObjects\ExchangeRate;
use App\Domain\Shared\Exceptions\DomainErrorCode;
use App\Domain\Shared\Exceptions\DomainException;

test('rate lookup result distinguishes exact fallback stale and unavailable results', function (): void {
    $quote = new ExchangeRateQuote(
        CurrencyCode::fromString('ETB'),
        CurrencyCode::fromString('USD'),
        ExchangeRate::fromDecimal('0.0071'),
        new DateTimeImmutable('2026-08-12T00:00:00Z'),
        'fixture',
        new DateTimeImmutable('2026-08-12T01:00:00Z'),
    );

    $this->assertSame(RateLookupStatus::Exact, RateLookupResult::exact($quote)->status);
    $this->assertSame(RateLookupStatus::LatestValid, RateLookupResult::latestValid($quote)->status);
    $this->assertSame(RateLookupStatus::Stale, RateLookupResult::stale($quote)->status);
    $this->assertFalse(RateLookupResult::unavailable()->hasRate());
});

test('locked override requires reference rate and reason and is immutable', function (): void {
    $etb = CurrencyCode::fromString('ETB');
    $usd = CurrencyCode::fromString('USD');

    try {
        new LockedExchangeRate(
            $etb,
            $usd,
            ExchangeRate::fromDecimal('0.0072'),
            new DateTimeImmutable('2026-08-12T00:00:00Z'),
            'manual',
            RoundingMode::HalfEven,
            null,
            'Manual correction',
        );
        $this->fail('A reference rate is required when an override reason is supplied.');
    } catch (DomainException $exception) {
        $this->assertSame(DomainErrorCode::InvalidRateLock, $exception->errorCode());
    }

    $locked = new LockedExchangeRate(
        $etb,
        $usd,
        ExchangeRate::fromDecimal('0.0072'),
        new DateTimeImmutable('2026-08-12T00:00:00Z'),
        'manual',
        RoundingMode::HalfEven,
        ExchangeRate::fromDecimal('0.0071'),
        'Provider quote corrected by receipt evidence',
    );

    $this->assertTrue($locked->isOverride());
    $this->assertSame('0.0072', $locked->usedRate->decimal());
});
