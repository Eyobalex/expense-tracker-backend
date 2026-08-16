<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateDevicePushTokenRequest;
use App\Http\Resources\Api\V1\DeviceResource;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $devices = $request->user()->devices()->latest('last_seen_at')->get();

        return $this->success($request, ['devices' => DeviceResource::collection($devices)->resolve($request)]);
    }

    public function destroy(Request $request, Device $device): JsonResponse
    {
        if ($request->user()->cannot('delete', $device)) {
            return $this->error($request, 'RESOURCE_NOT_FOUND', 'The requested resource was not found.', JsonResponse::HTTP_NOT_FOUND);
        }

        if ($device->personal_access_token_id !== null) {
            $request->user()->tokens()->whereKey($device->personal_access_token_id)->delete();
        }

        $device->update(['revoked_at' => now()]);

        return $this->success($request, ['revoked' => true]);
    }

    public function updatePushToken(UpdateDevicePushTokenRequest $request, Device $device): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        if ($request->user()->cannot('update', $device) || $device->personal_access_token_id !== $token->getKey() || $device->revoked_at !== null) {
            return $this->error($request, 'RESOURCE_NOT_FOUND', 'The requested resource was not found.', JsonResponse::HTTP_NOT_FOUND);
        }
        $attributes = $request->validated();
        $device->forceFill([
            'push_token' => $attributes['push_token'] ?? null,
            'push_token_provider' => isset($attributes['push_token']) ? ($attributes['provider'] ?? null) : null,
            'push_token_updated_at' => now(),
        ])->save();

        return $this->success($request, (new DeviceResource($device))->resolve($request));
    }
}
