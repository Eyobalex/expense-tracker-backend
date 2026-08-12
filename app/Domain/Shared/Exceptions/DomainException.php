<?php

namespace App\Domain\Shared\Exceptions;

use RuntimeException;

final class DomainException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    private function __construct(
        private readonly DomainErrorCode $errorCode,
        string $message,
        private readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function for(DomainErrorCode $errorCode, string $message, array $context = []): self
    {
        return new self($errorCode, $message, $context);
    }

    public function errorCode(): DomainErrorCode
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
