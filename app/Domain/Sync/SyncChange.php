<?php

namespace App\Domain\Sync;

use Carbon\CarbonImmutable;

final readonly class SyncChange
{
    /** @param array<string, mixed>|null $data */
    public function __construct(
        public string $resourceType,
        public string $resourceId,
        public string $operation,
        public int $version,
        public CarbonImmutable $changedAt,
        public ?array $data,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['resource_type' => $this->resourceType, 'resource_id' => $this->resourceId, 'operation' => $this->operation, 'version' => $this->version, 'changed_at' => $this->changedAt->toISOString(), 'data' => $this->data];
    }
}
