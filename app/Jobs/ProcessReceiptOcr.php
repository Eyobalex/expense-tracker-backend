<?php

namespace App\Jobs;

use App\Application\Receipts\ReceiptProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessReceiptOcr implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 75;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public string $receiptId, public string $requestId) {}

    public function handle(ReceiptProcessingService $processing): void
    {
        $processing->process($this->receiptId, $this->requestId, $this->job?->getJobId());
    }

    public function failed(\Throwable $exception): void
    {
        app(ReceiptProcessingService::class)->failed($this->receiptId, $this->requestId, $this->job?->getJobId(), $exception);
    }
}
