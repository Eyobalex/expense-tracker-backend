<?php

namespace App\Http\Resources\Api\V1;

use App\Models\SyncOperation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SyncOperation */
class SyncOperationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var SyncOperation $operation */
        $operation = $this->resource;

        return [
            'id' => $operation->id, 'operation_id' => $operation->client_operation_id, 'device_id' => $operation->device?->client_device_id,
            'entity' => $operation->entity, 'action' => $operation->action, 'local_id' => $operation->local_id, 'server_id' => $operation->server_id,
            'expected_version' => $operation->expected_version, 'status' => $operation->status, 'resource' => $operation->response_payload,
            'error' => $operation->error_code === null ? null : ['code' => $operation->error_code, 'fields' => $operation->error_fields ?? (object) []],
            'client_occurred_at' => $operation->client_occurred_at?->toISOString(), 'completed_at' => $operation->completed_at?->toISOString(),
            'created_at' => $operation->created_at?->toISOString(), 'updated_at' => $operation->updated_at?->toISOString(),
        ];
    }
}
