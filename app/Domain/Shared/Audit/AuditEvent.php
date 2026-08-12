<?php

namespace App\Domain\Shared\Audit;

use App\Domain\Shared\ValueObjects\OperationId;
use DateTimeImmutable;

final readonly class AuditEvent
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function __construct(
        public string $eventName,
        public string $aggregateType,
        public string $aggregateId,
        public ?int $actorUserId,
        public ?string $deviceId,
        public OperationId $operationId,
        public DateTimeImmutable $occurredAt,
        public array $before = [],
        public array $after = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toRedactedPayload(AuditPayloadRedactor $redactor): array
    {
        return [
            'event_name' => $this->eventName,
            'aggregate_type' => $this->aggregateType,
            'aggregate_id' => $this->aggregateId,
            'actor_user_id' => $this->actorUserId,
            'device_id' => $this->deviceId,
            'operation_id' => $this->operationId->toString(),
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
            'before' => $redactor->redact($this->before),
            'after' => $redactor->redact($this->after),
        ];
    }
}
