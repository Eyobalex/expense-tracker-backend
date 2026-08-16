<?php

namespace App\Domain\Notifications;

final readonly class NotificationDeliveryResult
{
    public function __construct(
        public bool $delivered,
        public ?string $failureCode = null,
    ) {}

    public static function delivered(): self
    {
        return new self(true);
    }

    public static function skipped(string $reason): self
    {
        return new self(false, $reason);
    }
}
