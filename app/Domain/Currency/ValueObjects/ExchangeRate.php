<?php

namespace App\Domain\Currency\ValueObjects;

use App\Domain\Accounting\ValueObjects\Money;
use App\Domain\Currency\Enums\RoundingMode;
use App\Domain\Shared\Exceptions\DomainErrorCode;
use App\Domain\Shared\Exceptions\DomainException;
use Brick\Math\BigDecimal;
use JsonSerializable;

final readonly class ExchangeRate implements JsonSerializable
{
    private function __construct(private BigDecimal $value) {}

    public static function fromDecimal(string $value): self
    {
        if (preg_match("/^0*(?:\.0+)?$/", $value) === 1 || preg_match("/^[0-9]+(?:\.[0-9]{1,18})?$/", $value) !== 1) {
            throw DomainException::for(DomainErrorCode::InvalidExchangeRate, 'Exchange rates must be a positive decimal with at most 18 fractional digits.');
        }

        return new self(BigDecimal::of($value));
    }

    public function convert(
        Money $money,
        CurrencyMetadata $sourceCurrency,
        CurrencyMetadata $targetCurrency,
        RoundingMode $roundingMode,
    ): Money {
        if (! $money->currency->equals($sourceCurrency->code)) {
            throw DomainException::for(DomainErrorCode::CurrencyMismatch, 'The money currency must match the source exchange-rate currency.');
        }

        $targetMinorUnits = BigDecimal::of($money->minorUnits)
            ->withPointMovedLeft($sourceCurrency->exponent)
            ->multipliedBy($this->value)
            ->withPointMovedRight($targetCurrency->exponent)
            ->toScale(0, $roundingMode->toBrickMath())
            ->toBigInteger()
            ->toInt();

        return new Money($targetMinorUnits, $targetCurrency->code);
    }

    public function decimal(): string
    {
        return (string) $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->decimal();
    }
}
