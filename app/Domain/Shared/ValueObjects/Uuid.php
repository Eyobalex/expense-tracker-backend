<?php

namespace App\Domain\Shared\ValueObjects;

use App\Domain\Shared\Exceptions\DomainErrorCode;
use App\Domain\Shared\Exceptions\DomainException;
use Illuminate\Support\Str;
use JsonSerializable;
use Stringable;

final readonly class Uuid implements JsonSerializable, Stringable
{
    private function __construct(private string $value) {}

    public static function generate(): self
    {
        return self::fromString((string) Str::uuid());
    }

    public static function fromString(string $value): self
    {
        $normalized = Str::lower($value);

        if (! Str::isUuid($normalized)) {
            throw DomainException::for(DomainErrorCode::InvalidUuid, 'A valid UUID is required.');
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
