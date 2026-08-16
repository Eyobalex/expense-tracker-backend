<?php

namespace App\Infrastructure\Notifications;

use App\Domain\Notifications\Contracts\NotificationChannel;
use App\Domain\Notifications\NotificationDeliveryResult;
use App\Models\NotificationDelivery;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class FirebaseCloudMessagingChannel implements NotificationChannel
{
    public function deliver(NotificationDelivery $delivery): NotificationDeliveryResult
    {
        $device = $delivery->device;
        $notification = $delivery->notification;
        $projectId = config('services.firebase.project_id');
        $accessToken = $this->accessToken();
        if (! is_string($projectId) || $projectId === '' || $accessToken === null) {
            return NotificationDeliveryResult::skipped('FCM_NOT_CONFIGURED');
        }
        if ($device === null || $device->push_token === null || $notification === null) {
            return NotificationDeliveryResult::skipped('FCM_DEVICE_TOKEN_UNAVAILABLE');
        }

        $response = Http::acceptJson()
            ->withToken($accessToken)
            ->timeout((int) config('services.firebase.timeout_seconds'))
            ->connectTimeout((int) config('services.firebase.connect_timeout_seconds'))
            ->retry([100, 500, 1000])
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => [
                    'token' => $device->push_token,
                    'notification' => ['title' => $notification->title, 'body' => $notification->body],
                    'data' => [
                        'notification_id' => $notification->getKey(),
                        'notification_type' => $notification->type,
                        'schema_version' => '1',
                    ],
                ],
            ]);
        if ($response->clientError()) {
            return NotificationDeliveryResult::skipped('FCM_DEVICE_TOKEN_REJECTED');
        }
        $response->throw();

        return NotificationDeliveryResult::delivered();
    }

    private function accessToken(): ?string
    {
        $staticToken = config('services.firebase.access_token');
        if (is_string($staticToken) && $staticToken !== '') {
            return $staticToken;
        }
        $credentialsPath = config('services.firebase.service_account_credentials_path');
        if (! is_string($credentialsPath) || $credentialsPath === '' || ! is_file($credentialsPath)) {
            return null;
        }
        $cacheKey = 'firebase:fcm:access-token:'.hash('sha256', $credentialsPath);

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($credentialsPath): ?string {
            $credentials = new ServiceAccountCredentials('https://www.googleapis.com/auth/firebase.messaging', $credentialsPath);
            $token = $credentials->fetchAuthToken();

            return is_string($token['access_token'] ?? null) ? $token['access_token'] : null;
        });
    }
}
