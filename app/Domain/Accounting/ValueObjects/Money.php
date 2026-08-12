<?php

namespace App\Domain\Accounting\ValueObjects;

use App\Domain\Currency\ValueObjects\CurrencyCode;
use App\Domain\Shared\Exceptions\DomainErrorCode;
use App\Domain\Shared\Exceptions\DomainException;
use JsonSerializable;

final readonly class Money implements JsonSerializable
{
    public function __construct(
        public int $minorUnits,
        public CurrencyCode $currency,
    ) {}

    public static function zero(CurrencyCode $currency): self
    {
        return new self(0, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits + $other->minorUnits, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits - $other->minorUnits, $this->currency);
    }

    public function isPositive(): bool
    {
        return $this->minorUnits > 0;
    }

    public function isZero(): bool
    {
        return $this->minorUnits === 0;
    }

    /**
     * @return array{minor_units: int, currency: string}
     */
    public function jsonSerialize(): array
    {
        return ['minor_units' => $this->minorUnits, 'currency' => $this->currency->toString()];
    }

    private function assertSameCurrency(self $other): void
    {
        if (! $this->currency->equals($other->currency)) {
            throw DomainException::for(DomainErrorCode::CurrencyMismatch, 'Money arithmetic requires matching currencies.');
        }
    }
}
