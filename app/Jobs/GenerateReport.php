<?php

namespace App\Jobs;

use App\Application\Reporting\ReportService;
use App\Models\ReportJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateReport implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 600];

    public int $uniqueFor = 3600;

    public function __construct(public string $reportJobId) {}

    public function uniqueId(): string
    {
        return $this->reportJobId;
    }

    public function handle(ReportService $reports): void
    {
        $reports->generate($this->reportJobId, $this->job?->getJobId());
    }

    public function failed(\Throwable $exception): void
    {
        $report = ReportJob::query()->find($this->reportJobId);
        if ($report instanceof ReportJob) {
            app(ReportService::class)->fail($report, $exception);
        }
    }
}
