<?php

namespace App\Domain\Currency\ValueObjects;

use App\Domain\Shared\Exceptions\DomainErrorCode;
use App\Domain\Shared\Exceptions\DomainException;

final readonly class CurrencyMetadata
{
    public function __construct(
        public CurrencyCode $code,
        public int $exponent,
        public ?string $displayName = null,
        public ?string $symbol = null,
    ) {
        if ($exponent < 0 || $exponent > 9) {
            throw DomainException::for(DomainErrorCode::InvalidCurrencyExponent, 'Currency exponent must be between 0 and 9.');
        }
    }
}
