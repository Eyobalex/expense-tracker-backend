<?php

namespace App\Domain\Shared\Events;

interface DomainEventBus
{
    public function dispatch(DomainEvent $event): void;
}
