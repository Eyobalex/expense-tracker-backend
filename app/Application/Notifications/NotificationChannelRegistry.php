<?php

namespace App\Application\Notifications;

use App\Domain\Notifications\Contracts\NotificationChannel as NotificationChannelContract;
use App\Domain\Notifications\Enums\NotificationChannel;
use App\Infrastructure\Notifications\EmailNotificationChannel;
use App\Infrastructure\Notifications\FirebaseCloudMessagingChannel;
use App\Infrastructure\Notifications\LocalNotificationChannel;

final readonly class NotificationChannelRegistry
{
    public function __construct(
        private LocalNotificationChannel $local,
        private FirebaseCloudMessagingChannel $push,
        private EmailNotificationChannel $email,
    ) {}

    public function for(NotificationChannel $channel): ?NotificationChannelContract
    {
        return match ($channel) {
            NotificationChannel::InApp => null,
            NotificationChannel::Local => $this->local,
            NotificationChannel::Push => $this->push,
            NotificationChannel::Email => $this->email,
        };
    }
}
