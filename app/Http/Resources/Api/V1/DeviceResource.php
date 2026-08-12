<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Device */
class DeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Device $device */
        $device = $this->resource;

        return [
            'id' => $device->id,
            'client_device_id' => $device->client_device_id,
            'platform' => $device->platform,
            'app_version' => $device->app_version,
            'last_seen_at' => $device->last_seen_at?->toISOString(),
            'revoked_at' => $device->revoked_at?->toISOString(),
        ];
    }
}
