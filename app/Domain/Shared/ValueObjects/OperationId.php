<?php

namespace App\Domain\Shared\ValueObjects;

use App\Domain\Shared\Exceptions\DomainErrorCode;
use App\Domain\Shared\Exceptions\DomainException;
use JsonSerializable;
use Stringable;

final readonly class OperationId implements JsonSerializable, Stringable
{
    private function __construct(private Uuid $value) {}

    public static function generate(): self
    {
        return new self(Uuid::generate());
    }

    public static function fromString(string $value): self
    {
        try {
            return new self(Uuid::fromString($value));
        } catch (DomainException) {
            throw DomainException::for(DomainErrorCode::InvalidOperationId, 'A valid operation UUID is required.');
        }
    }

    public function equals(self $other): bool
    {
        return $this->value->equals($other->value);
    }

    public function jsonSerialize(): string
    {
        return $this->toString();
    }

    public function toString(): string
    {
        return $this->value->toString();
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
