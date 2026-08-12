<?php

namespace App\Application\Currency;

use App\Domain\Accounting\ValueObjects\Money;
use App\Domain\Currency\Enums\RoundingMode;
use App\Domain\Currency\LockedExchangeRate;
use App\Domain\Currency\ValueObjects\CurrencyCode;
use App\Domain\Currency\ValueObjects\ExchangeRate;
use App\Domain\Shared\Exceptions\DomainErrorCode;
use App\Domain\Shared\Exceptions\DomainException;
use DateTimeImmutable;

final readonly class RateLockService
{
    public function __construct(
        private CurrencyRegistry $currencies,
        private CurrencyPolicy $policy,
    ) {}

    public function lock(
        CurrencyCode $baseCurrency,
        CurrencyCode $quoteCurrency,
        string $usedRate,
        DateTimeImmutable $rateDate,
        string $source,
        ?RoundingMode $roundingMode = null,
        ?string $referenceRate = null,
        ?string $overrideReason = null,
    ): LockedExchangeRate {
        $this->policy->assertSupportedPair($baseCurrency, $quoteCurrency);

        if (trim($source) === '') {
            throw DomainException::for(DomainErrorCode::InvalidRateLock, 'An exchange-rate source is required.');
        }

        $reference = $referenceRate === null ? null : ExchangeRate::fromDecimal($referenceRate);

        return new LockedExchangeRate(
            $baseCurrency,
            $quoteCurrency,
            ExchangeRate::fromDecimal($usedRate),
            $rateDate,
            trim($source),
            $roundingMode ?? $this->policy->roundingMode(),
            $reference,
            $overrideReason === null ? null : trim($overrideReason),
        );
    }

    public function convertToBase(Money $originalAmount, LockedExchangeRate $lockedRate): Money
    {
        if (! $originalAmount->currency->equals($lockedRate->baseCurrency)) {
            throw DomainException::for(DomainErrorCode::CurrencyMismatch, 'The original amount currency must match the locked rate base currency.');
        }

        return $lockedRate->usedRate->convert(
            $originalAmount,
            $this->currencies->activeMetadata($lockedRate->baseCurrency),
            $this->currencies->activeMetadata($lockedRate->quoteCurrency),
            $lockedRate->roundingMode,
        );
    }
}
