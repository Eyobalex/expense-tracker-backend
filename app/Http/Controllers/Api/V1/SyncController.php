<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Sync\SyncService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SyncPushRequest;
use App\Http\Resources\Api\V1\SyncOperationResource;
use App\Models\SyncOperation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function push(SyncPushRequest $request, SyncService $sync): JsonResponse
    {
        /** @var array<string, mixed> $attributes */
        $attributes = $request->validated();
        $operation = $sync->push($request->user(), $attributes);

        return $this->success($request, (new SyncOperationResource($operation))->resolve($request), JsonResponse::HTTP_ACCEPTED);
    }

    public function pull(Request $request, SyncService $sync): JsonResponse
    {
        $request->validate(['cursor' => ['nullable', 'string', 'max:4096'], 'full' => ['nullable', 'boolean'], 'limit' => ['nullable', 'integer', 'min:1', 'max:250']]);
        $result = $sync->pull($request->user(), $request->string('cursor')->trim()->toString() ?: null, $request->boolean('full'), $request->integer('limit') ?: null);

        return $this->success($request, $result);
    }

    public function show(Request $request, SyncOperation $operation): JsonResponse
    {
        $ownedOperation = SyncOperation::query()->ownedBy($request->user())->find($operation->getKey());
        if (! $ownedOperation instanceof SyncOperation) {
            return $this->error($request, 'RESOURCE_NOT_FOUND', 'The requested resource was not found.', JsonResponse::HTTP_NOT_FOUND);
        }

        return $this->success($request, (new SyncOperationResource($ownedOperation))->resolve($request));
    }
}
