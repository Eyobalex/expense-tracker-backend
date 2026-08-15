<?php

namespace App\Jobs;

use App\Application\Currency\ExchangeRateRefreshService;
use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshExchangeRates implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public int $timeout = 60;

    /** @var array<int, int> */
    public array $backoff = [60, 120, 240];

    public function __construct(public string $rateDate) {}

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('fx:refresh:'.$this->rateDate))->expireAfter(120)];
    }

    public function handle(ExchangeRateRefreshService $rates): void
    {
        $rates->refresh(new DateTimeImmutable($this->rateDate));
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('fx.rate_refresh_failed', [
            'provider' => config('fx.provider'),
            'rate_date' => $this->rateDate,
            'job_id' => $this->job?->getJobId(),
            'exception' => $exception::class,
        ]);
    }
}
