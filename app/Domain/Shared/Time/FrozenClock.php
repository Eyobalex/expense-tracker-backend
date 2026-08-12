<?php

namespace App\Domain\Shared\Time;

use DateTimeImmutable;

final class FrozenClock implements Clock
{
    public function __construct(private DateTimeImmutable $currentTime) {}

    public function now(): DateTimeImmutable
    {
        return $this->currentTime;
    }

    public function travelTo(DateTimeImmutable $instant): void
    {
        $this->currentTime = $instant;
    }
}
