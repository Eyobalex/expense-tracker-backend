<?php

namespace App\Domain\Notifications\Contracts;

use App\Domain\Notifications\NotificationDeliveryResult;
use App\Models\NotificationDelivery;

interface NotificationChannel
{
    public function deliver(NotificationDelivery $delivery): NotificationDeliveryResult;
}
