<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Resources\Api\V1\ProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return $this->success($request, (new ProfileResource($request->user()))->resolve($request));
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $expectedVersion = $request->header('If-Match');

        if (! is_string($expectedVersion) || ! ctype_digit($expectedVersion)) {
            return $this->error($request, 'STALE_VERSION', 'The resource version is stale.', JsonResponse::HTTP_CONFLICT);
        }

        $updated = $user->newQuery()
            ->whereKey($user->getKey())
            ->where('version', (int) $expectedVersion)
            ->update([...$request->validated(), 'version' => (int) $expectedVersion + 1]);

        if ($updated !== 1) {
            return $this->error($request, 'STALE_VERSION', 'The resource version is stale.', JsonResponse::HTTP_CONFLICT);
        }

        return $this->success($request, (new ProfileResource($user->fresh()))->resolve($request));
    }
}
