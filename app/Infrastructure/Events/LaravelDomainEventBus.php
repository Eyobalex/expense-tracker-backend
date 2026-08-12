<?php

namespace App\Infrastructure\Events;

use App\Domain\Shared\Events\DomainEvent;
use App\Domain\Shared\Events\DomainEventBus;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class LaravelDomainEventBus implements DomainEventBus
{
    public function __construct(private Dispatcher $events) {}

    public function dispatch(DomainEvent $event): void
    {
        $this->events->dispatch($event);
    }
}
