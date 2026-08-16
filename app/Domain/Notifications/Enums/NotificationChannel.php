<?php

namespace App\Domain\Notifications\Enums;

enum NotificationChannel: string
{
    case InApp = 'in_app';
    case Local = 'local';
    case Push = 'push';
    case Email = 'email';
}
