<?php

namespace App\Infrastructure\Notifications;

use App\Domain\Notifications\Contracts\NotificationChannel;
use App\Domain\Notifications\NotificationDeliveryResult;
use App\Mail\FinancialInsightNotificationMail;
use App\Models\NotificationDelivery;
use Illuminate\Support\Facades\Mail;

final class EmailNotificationChannel implements NotificationChannel
{
    public function deliver(NotificationDelivery $delivery): NotificationDeliveryResult
    {
        $notification = $delivery->notification;
        $user = $notification?->user;
        if ($user === null) {
            return NotificationDeliveryResult::skipped('NOTIFICATION_RECIPIENT_UNAVAILABLE');
        }

        Mail::to($user->email)->send(new FinancialInsightNotificationMail($notification));

        return NotificationDeliveryResult::delivered();
    }
}
