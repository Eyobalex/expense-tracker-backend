<?php

namespace App\Infrastructure\Notifications;

use App\Domain\Notifications\Contracts\NotificationChannel;
use App\Domain\Notifications\NotificationDeliveryResult;
use App\Models\NotificationDelivery;

final class LocalNotificationChannel implements NotificationChannel
{
    public function deliver(NotificationDelivery $delivery): NotificationDeliveryResult
    {
        return $delivery->device_id === null
            ? NotificationDeliveryResult::skipped('LOCAL_DEVICE_UNAVAILABLE')
            : NotificationDeliveryResult::delivered();
    }
}
