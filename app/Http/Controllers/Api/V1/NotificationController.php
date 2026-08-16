<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Notifications\NotificationInboxService;
use App\Application\Notifications\NotificationPreferences;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListNotificationsRequest;
use App\Http\Requests\Api\V1\MarkNotificationReadRequest;
use App\Http\Requests\Api\V1\UpdateNotificationPreferencesRequest;
use App\Http\Resources\Api\V1\UserNotificationResource;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(ListNotificationsRequest $request, NotificationInboxService $inbox): JsonResponse
    {
        $notifications = $inbox->inbox($request->user(), $request->boolean('unread_only'), $request->integer('limit') ?: 50);

        return $this->success($request, ['notifications' => UserNotificationResource::collection($notifications)->resolve($request)]);
    }

    public function preferences(Request $request, NotificationPreferences $preferences): JsonResponse
    {
        return $this->success($request, ['preferences' => $preferences->normalized($request->user())]);
    }

    public function updatePreferences(UpdateNotificationPreferencesRequest $request, NotificationPreferences $preferences): JsonResponse
    {
        $user = $request->user();
        $expectedVersion = $this->expectedVersion($request);
        if ($expectedVersion === null) {
            return $this->staleVersion($request);
        }
        $updated = $user->newQuery()
            ->whereKey($user->getKey())
            ->where('version', $expectedVersion)
            ->update(['notification_preferences' => $preferences->merged($user, $request->validated()), 'version' => $expectedVersion + 1]);
        if ($updated !== 1) {
            return $this->staleVersion($request);
        }

        return $this->success($request, ['preferences' => $preferences->normalized($user->fresh())]);
    }

    public function markRead(MarkNotificationReadRequest $request, UserNotification $notification, NotificationInboxService $inbox): JsonResponse
    {
        if ($request->user()->cannot('update', $notification)) {
            return $this->error($request, 'RESOURCE_NOT_FOUND', 'The requested resource was not found.', JsonResponse::HTTP_NOT_FOUND);
        }
        $expectedVersion = $this->expectedVersion($request);
        if ($expectedVersion === null) {
            return $this->staleVersion($request);
        }

        return $this->success($request, (new UserNotificationResource($inbox->markRead($request->user(), $notification, $expectedVersion)))->resolve($request));
    }

    private function expectedVersion(Request $request): ?int
    {
        $value = $request->header('If-Match');

        return is_string($value) && ctype_digit($value) ? (int) $value : null;
    }

    private function staleVersion(Request $request): JsonResponse
    {
        return $this->error($request, 'STALE_VERSION', 'The resource version is stale.', JsonResponse::HTTP_CONFLICT);
    }
}
