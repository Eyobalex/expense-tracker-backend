<?php

namespace App\Application\Notifications;

use App\Application\Insights\InsightSnapshotService;
use App\Domain\Insights\Enums\FormulaName;
use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationDeliveryStatus;
use App\Domain\Notifications\Enums\NotificationType;
use App\Jobs\DeliverUserNotification;
use App\Models\AuditEvent;
use App\Models\Device;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Models\UserNotification;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class NotificationService
{
    public function __construct(
        private NotificationPreferences $preferences,
        private InsightSnapshotService $snapshots,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $insight
     */
    public function create(User $user, NotificationType $type, string $deduplicationKey, string $title, string $body, array $payload, ?array $insight = null, ?DateTimeInterface $coveredFrom = null, ?DateTimeInterface $coveredUntil = null, ?string $requestId = null): ?UserNotification
    {
        try {
            return $this->persist($user, $type, $deduplicationKey, $title, $body, $payload, $insight, $coveredFrom, $coveredUntil, $requestId);
        } catch (\Throwable $exception) {
            Log::warning('notification.creation_failed', [
                'user_id' => $user->getKey(),
                'notification_type' => $type->value,
                'request_id' => $requestId,
                'failure_code' => class_basename($exception),
            ]);
            try {
                AuditEvent::query()->create([
                    'user_id' => $user->getKey(),
                    'event_name' => 'notification.creation_failed',
                    'aggregate_type' => 'user',
                    'aggregate_id' => (string) $user->getKey(),
                    'request_id' => $requestId,
                    'summary' => ['notification_type' => $type->value, 'failure_code' => class_basename($exception)],
                ]);
            } catch (\Throwable) {
            }

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $insight
     */
    private function persist(User $user, NotificationType $type, string $deduplicationKey, string $title, string $body, array $payload, ?array $insight, ?DateTimeInterface $coveredFrom, ?DateTimeInterface $coveredUntil, ?string $requestId): ?UserNotification
    {
        if (! $this->hasAnyEnabledChannel($user, $type)) {
            return null;
        }
        /** @var array{notification: UserNotification, created: bool, delivery_ids: list<string>} $result */
        $result = Cache::lock('notification:create:'.$user->getKey().':'.hash('sha256', $deduplicationKey), 10)->block(5, function () use ($user, $type, $deduplicationKey, $title, $body, $payload, $insight, $coveredFrom, $coveredUntil, $requestId): array {
            return DB::transaction(function () use ($user, $type, $deduplicationKey, $title, $body, $payload, $insight, $coveredFrom, $coveredUntil, $requestId): array {
                $existing = UserNotification::query()
                    ->ownedBy($user)
                    ->where('deduplication_key', $deduplicationKey)
                    ->lockForUpdate()
                    ->first();
                if ($existing instanceof UserNotification) {
                    return ['notification' => $existing, 'created' => false, 'delivery_ids' => []];
                }
                $snapshotId = null;
                if ($insight !== null && isset($insight['formula']['name'], $insight['timezone'], $insight['calculated_at'])) {
                    $formula = FormulaName::from((string) $insight['formula']['name']);
                    $snapshot = $this->snapshots->persist(
                        $user,
                        $formula,
                        (string) $insight['timezone'],
                        is_string($insight['base_currency_code'] ?? null) ? $insight['base_currency_code'] : null,
                        new \DateTimeImmutable((string) $insight['calculated_at']),
                        ['notification_type' => $type->value, 'deduplication_key' => $deduplicationKey],
                        $insight,
                        $coveredFrom,
                        $coveredUntil,
                    );
                    $snapshotId = $snapshot->getKey();
                }
                $notification = $user->userNotifications()->create([
                    'type' => $type->value,
                    'title' => $title,
                    'body' => $body,
                    'payload' => ['schema_version' => 1, ...$payload],
                    'in_app_enabled' => $this->preferences->channelEnabled($user, $type, NotificationChannel::InApp),
                    'insight_snapshot_id' => $snapshotId,
                    'deduplication_key' => $deduplicationKey,
                    'request_id' => $requestId,
                ]);
                $deliveryIds = $this->createDeliveries($notification, $user, $type);
                AuditEvent::query()->create([
                    'user_id' => $user->getKey(),
                    'event_name' => 'notification.created',
                    'aggregate_type' => 'user_notification',
                    'aggregate_id' => $notification->getKey(),
                    'request_id' => $requestId,
                    'summary' => ['notification_type' => $type->value, 'delivery_count' => count($deliveryIds)],
                ]);

                return ['notification' => $notification, 'created' => true, 'delivery_ids' => $deliveryIds];
            }, attempts: 3);
        });
        if ($result['created']) {
            foreach ($result['delivery_ids'] as $deliveryId) {
                DeliverUserNotification::dispatch($deliveryId)->afterCommit();
            }
        }

        return $result['notification'];
    }

    /** @return list<string> */
    private function createDeliveries(UserNotification $notification, User $user, NotificationType $type): array
    {
        $deliveries = collect();
        foreach (NotificationChannel::cases() as $channel) {
            if (! $this->preferences->channelEnabled($user, $type, $channel)) {
                continue;
            }
            if ($channel === NotificationChannel::InApp) {
                $deliveries->push($this->newDelivery($notification, $channel));

                continue;
            }
            if ($channel === NotificationChannel::Email) {
                $deliveries->push($this->newDelivery($notification, $channel));

                continue;
            }
            $devices = $user->devices()
                ->whereNull('revoked_at')
                ->when($channel === NotificationChannel::Push, fn ($query) => $query->whereNotNull('push_token')->where('push_token_provider', 'fcm'))
                ->get();
            if ($devices->isEmpty()) {
                $deliveries->push($this->newDelivery($notification, $channel));
            }
            foreach ($devices as $device) {
                $deliveries->push($this->newDelivery($notification, $channel, $device));
            }
        }

        $deliveryIds = [];
        foreach ($deliveries as $delivery) {
            if ($delivery instanceof NotificationDelivery && $delivery->channel !== NotificationChannel::InApp->value) {
                $deliveryIds[] = $delivery->getKey();
            }
        }

        return $deliveryIds;
    }

    private function newDelivery(UserNotification $notification, NotificationChannel $channel, ?Device $device = null): NotificationDelivery
    {
        $delivery = $notification->deliveries()->create([
            'device_id' => $device?->getKey(),
            'channel' => $channel->value,
            'status' => $channel === NotificationChannel::InApp ? NotificationDeliveryStatus::Delivered->value : NotificationDeliveryStatus::Queued->value,
            'idempotency_key' => hash('sha256', $notification->getKey().'|'.$channel->value.'|'.($device?->getKey() ?? 'user')),
            'delivered_at' => $channel === NotificationChannel::InApp ? now() : null,
        ]);

        return $delivery;
    }

    private function hasAnyEnabledChannel(User $user, NotificationType $type): bool
    {
        foreach (NotificationChannel::cases() as $channel) {
            if ($this->preferences->channelEnabled($user, $type, $channel)) {
                return true;
            }
        }

        return false;
    }
}
