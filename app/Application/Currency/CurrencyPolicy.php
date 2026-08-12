<?php

namespace App\Application\Currency;

use App\Domain\Currency\Enums\RoundingMode;
use App\Domain\Currency\ValueObjects\CurrencyCode;
use App\Domain\Shared\Exceptions\DomainErrorCode;
use App\Domain\Shared\Exceptions\DomainException;

final readonly class CurrencyPolicy
{
    public const RATE_SCALE = 18;

    public const ROUNDING_TOLERANCE_MINOR_UNITS = 1;

    public function __construct(private CurrencyRegistry $currencies) {}

    public function roundingMode(): RoundingMode
    {
        return RoundingMode::HalfEven;
    }

    public function roundingToleranceMinorUnits(): int
    {
        return self::ROUNDING_TOLERANCE_MINOR_UNITS;
    }

    public function assertSupportedPair(CurrencyCode $baseCurrency, CurrencyCode $quoteCurrency): void
    {
        $this->currencies->activeMetadata($baseCurrency);
        $this->currencies->activeMetadata($quoteCurrency);

        if ($baseCurrency->equals($quoteCurrency)) {
            throw DomainException::for(DomainErrorCode::InvalidRateLock, 'An exchange-rate pair requires two different currencies.');
        }
    }
}
