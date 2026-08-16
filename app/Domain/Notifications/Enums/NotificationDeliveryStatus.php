<?php

namespace App\Domain\Notifications\Enums;

enum NotificationDeliveryStatus: string
{
    case Queued = 'queued';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
