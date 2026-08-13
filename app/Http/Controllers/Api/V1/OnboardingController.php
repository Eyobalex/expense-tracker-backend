<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Identity\OnboardingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateOnboardingRequest;
use App\Http\Resources\Api\V1\OnboardingResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return $this->success($request, (new OnboardingResource($request->user()))->resolve($request));
    }

    public function update(UpdateOnboardingRequest $request, OnboardingService $onboarding): JsonResponse
    {
        $expectedVersion = $request->header('If-Match');
        if (! is_string($expectedVersion) || ! ctype_digit($expectedVersion) || (int) $expectedVersion !== $request->user()->version) {
            return $this->error($request, 'STALE_VERSION', 'The resource version is stale.', JsonResponse::HTTP_CONFLICT);
        }

        /** @var array{base_currency_code: string, timezone: string, budget_timezone: string, notification_preferences?: array<string, mixed>, seed_starter_data?: bool} $attributes */
        $attributes = $request->validated();
        $user = $onboarding->update($request->user(), (int) $expectedVersion, $attributes);

        return $this->success($request, (new OnboardingResource($user))->resolve($request));
    }
}
