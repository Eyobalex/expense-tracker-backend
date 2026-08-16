<?php

namespace App\Http\Resources\Api\V1;

use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin UserNotification */
class UserNotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var UserNotification $notification */
        $notification = $this->resource;

        return [
            'id' => $notification->getKey(),
            'type' => $notification->type,
            'title' => $notification->title,
            'body' => $notification->body,
            'payload' => $notification->payload,
            'read_at' => $notification->read_at?->toISOString(),
            'version' => $notification->version,
            'created_at' => $notification->created_at?->toISOString(),
            'deliveries' => $this->deliveries($notification),
        ];
    }

    /** @return list<array{channel: string, status: string, device_id: string|null, delivered_at: string|null}> */
    private function deliveries(UserNotification $notification): array
    {
        if (! $notification->relationLoaded('deliveries')) {
            return [];
        }
        $deliveries = [];
        foreach ($notification->deliveries as $delivery) {
            $deliveries[] = [
                'channel' => $delivery->channel,
                'status' => $delivery->status,
                'device_id' => $delivery->device_id,
                'delivered_at' => $delivery->delivered_at?->toISOString(),
            ];
        }

        return $deliveries;
    }
}
