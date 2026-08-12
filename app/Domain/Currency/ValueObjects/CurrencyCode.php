<?php

namespace App\Domain\Currency\ValueObjects;

use App\Domain\Shared\Exceptions\DomainErrorCode;
use App\Domain\Shared\Exceptions\DomainException;
use JsonSerializable;
use Stringable;

final readonly class CurrencyCode implements JsonSerializable, Stringable
{
    private function __construct(private string $value) {}

    public static function fromString(string $value): self
    {
        $normalized = strtoupper(trim($value));

        if (preg_match('/^[A-Z]{3}$/', $normalized) !== 1) {
            throw DomainException::for(DomainErrorCode::InvalidCurrencyCode, 'An ISO 4217 three-letter currency code is required.');
        }

        return new self($normalized);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
