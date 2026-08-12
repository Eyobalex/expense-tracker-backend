<?php

namespace App\Domain\Shared\Events;

use DateTimeImmutable;

interface DomainEvent
{
    public function occurredAt(): DateTimeImmutable;

    public function eventName(): string;
}
