<?php

namespace App\Jobs;

use App\Application\Notifications\NotificationDeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeliverUserNotification implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public int $uniqueFor = 120;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public string $deliveryId) {}

    public function uniqueId(): string
    {
        return $this->deliveryId;
    }

    public function handle(NotificationDeliveryService $deliveries): void
    {
        $deliveries->deliver($this->deliveryId, $this->job?->getJobId());
    }

    public function failed(\Throwable $exception): void
    {
        app(NotificationDeliveryService::class)->failed($this->deliveryId, $exception);
    }
}
