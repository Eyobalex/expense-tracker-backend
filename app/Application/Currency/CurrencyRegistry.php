<?php

namespace App\Application\Currency;

use App\Domain\Currency\ValueObjects\CurrencyCode;
use App\Domain\Currency\ValueObjects\CurrencyMetadata;
use App\Domain\Shared\Exceptions\DomainErrorCode;
use App\Domain\Shared\Exceptions\DomainException;
use App\Models\Currency;

final readonly class CurrencyRegistry
{
    public function activeMetadata(string|CurrencyCode $currency): CurrencyMetadata
    {
        $code = $currency instanceof CurrencyCode ? $currency : CurrencyCode::fromString($currency);
        $record = Currency::query()->find($code->toString());

        if (! $record instanceof Currency) {
            throw DomainException::for(DomainErrorCode::UnsupportedCurrency, 'The currency is not supported.');
        }

        if (! $record->is_active) {
            throw DomainException::for(DomainErrorCode::InactiveCurrency, 'The currency is not active.');
        }

        return $record->metadata();
    }

    public function isActive(string|CurrencyCode $currency): bool
    {
        try {
            $this->activeMetadata($currency);
        } catch (DomainException) {
            return false;
        }

        return true;
    }
}
