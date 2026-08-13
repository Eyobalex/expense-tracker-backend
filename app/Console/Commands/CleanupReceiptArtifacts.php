<?php

namespace App\Console\Commands;

use App\Models\Receipt;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('receipt:cleanup-artifacts {--dry-run}')]
#[Description('Clean up expired abandoned receipt source and derivative objects')]
class CleanupReceiptArtifacts extends Command
{
    public function handle(): int
    {
        $days = (int) config('receipts.abandoned_retention_days', 30);
        $cutoff = now()->subDays($days);
        $disk = Storage::disk((string) config('receipts.disk'));
        $cleaned = 0;
        foreach (Receipt::query()->where('status', 'abandoned')->where('abandoned_at', '<=', $cutoff)->whereNull('deleted_at')->with('derivatives')->cursor() as $receipt) {
            $cleaned++;
            if (! $this->option('dry-run')) {
                $disk->delete([$receipt->original_object_key, ...$receipt->derivatives->pluck('object_key')->all()]);
                $receipt->forceFill(['status' => 'deleted', 'deleted_at' => now()])->save();
            }
        }
        $this->info("Receipt artifacts cleaned: {$cleaned}.");

        return self::SUCCESS;
    }
}
