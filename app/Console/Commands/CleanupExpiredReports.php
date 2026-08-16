<?php

namespace App\Console\Commands;

use App\Models\ReportJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

#[Signature('reports:cleanup {--dry-run}')]
#[Description('Delete expired private report and export artifacts')]
class CleanupExpiredReports extends Command
{
    public function handle(): int
    {
        $reports = ReportJob::query()->whereIn('status', ['queued', 'processing', 'completed', 'failed'])->where('expires_at', '<=', now())->cursor();
        $disk = Storage::disk((string) config('reports.disk'));
        $count = 0;
        foreach ($reports as $report) {
            $count++;
            if (! $this->option('dry-run')) {
                if ($report->private_object_key !== null) {
                    $disk->delete($report->private_object_key);
                }
                $report->forceFill(['status' => 'expired', 'progress' => 100, 'private_object_key' => null, 'deleted_at' => now()])->save();
            }
        }
        Log::info('report.expiry_cleanup_completed', ['report_count' => $count, 'dry_run' => (bool) $this->option('dry-run')]);
        $this->info("Expired report artifacts cleaned: {$count}.");

        return self::SUCCESS;
    }
}
