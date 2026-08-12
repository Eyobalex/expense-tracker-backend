<?php

namespace App\Domain\Currency;

use App\Domain\Currency\Enums\RoundingMode;
use App\Domain\Currency\ValueObjects\CurrencyCode;
use App\Domain\Currency\ValueObjects\ExchangeRate;
use App\Domain\Shared\Exceptions\DomainErrorCode;
use App\Domain\Shared\Exceptions\DomainException;
use DateTimeImmutable;

final readonly class LockedExchangeRate
{
    public function __construct(
        public CurrencyCode $baseCurrency,
        public CurrencyCode $quoteCurrency,
        public ExchangeRate $usedRate,
        public DateTimeImmutable $rateDate,
        public string $source,
        public RoundingMode $roundingMode,
        public ?ExchangeRate $referenceRate = null,
        public ?string $overrideReason = null,
    ) {
        if ($baseCurrency->equals($quoteCurrency)) {
            throw DomainException::for(DomainErrorCode::InvalidRateLock, 'A locked exchange rate requires different currencies.');
        }

        if ($overrideReason !== null && $referenceRate === null) {
            throw DomainException::for(DomainErrorCode::InvalidRateLock, 'An exchange-rate override requires a reference rate.');
        }
    }

    public function isOverride(): bool
    {
        return $this->referenceRate !== null && $this->referenceRate->decimal() !== $this->usedRate->decimal();
    }
}
