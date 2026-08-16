<?php

namespace App\Application\Notifications;

use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationDeliveryStatus;
use App\Models\AuditEvent;
use App\Models\NotificationDelivery;
use Illuminate\Support\Facades\DB;

final readonly class NotificationDeliveryService
{
    public function __construct(private NotificationChannelRegistry $channels) {}

    public function deliver(string $deliveryId, ?string $jobId = null): void
    {
        /** @var NotificationDelivery|null $delivery */
        $delivery = DB::transaction(function () use ($deliveryId, $jobId): ?NotificationDelivery {
            $locked = NotificationDelivery::query()
                ->with(['notification.user', 'device'])
                ->lockForUpdate()
                ->find($deliveryId);
            if (! $locked instanceof NotificationDelivery || in_array($locked->status, [NotificationDeliveryStatus::Delivered->value, NotificationDeliveryStatus::Skipped->value], true)) {
                return null;
            }
            $locked->forceFill([
                'attempt_count' => $locked->attempt_count + 1,
                'job_id' => $jobId,
                'dispatched_at' => now(),
                'failure_code' => null,
            ])->save();

            return $locked;
        }, attempts: 3);
        if (! $delivery instanceof NotificationDelivery) {
            return;
        }
        $channel = $this->channels->for(NotificationChannel::from($delivery->channel));
        if ($channel === null) {
            $this->complete($delivery, NotificationDeliveryStatus::Delivered);

            return;
        }
        $result = $channel->deliver($delivery);
        $this->complete(
            $delivery,
            $result->delivered ? NotificationDeliveryStatus::Delivered : NotificationDeliveryStatus::Skipped,
            $result->failureCode,
        );
    }

    public function failed(string $deliveryId, \Throwable $exception): void
    {
        DB::transaction(function () use ($deliveryId, $exception): void {
            $delivery = NotificationDelivery::query()->with('notification')->lockForUpdate()->find($deliveryId);
            if (! $delivery instanceof NotificationDelivery || $delivery->status === NotificationDeliveryStatus::Delivered->value) {
                return;
            }
            $delivery->forceFill([
                'status' => NotificationDeliveryStatus::Failed->value,
                'failed_at' => now(),
                'failure_code' => class_basename($exception),
            ])->save();
            if ($delivery->notification !== null) {
                AuditEvent::query()->create([
                    'user_id' => $delivery->notification->user_id,
                    'event_name' => 'notification.delivery_failed',
                    'aggregate_type' => 'user_notification',
                    'aggregate_id' => $delivery->notification->getKey(),
                    'summary' => ['delivery_id' => $delivery->getKey(), 'channel' => $delivery->channel, 'failure_code' => class_basename($exception)],
                ]);
            }
        }, attempts: 3);
    }

    private function complete(NotificationDelivery $delivery, NotificationDeliveryStatus $status, ?string $failureCode = null): void
    {
        $delivery->forceFill([
            'status' => $status->value,
            'delivered_at' => $status === NotificationDeliveryStatus::Delivered ? now() : null,
            'failure_code' => $failureCode,
        ])->save();
    }
}
