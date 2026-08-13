<?php

namespace App\Console\Commands;

use App\Models\AuditEvent;
use App\Models\Receipt;
use App\Models\ReceiptDerivative;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('receipt:reconcile-storage {--dry-run}')]
#[Description('Reconcile receipt database records with private object storage')]
class ReconcileReceiptStorage extends Command
{
    public function handle(): int
    {
        $disk = Storage::disk((string) config('receipts.disk'));
        $missing = 0;
        foreach (Receipt::query()->whereNull('deleted_at')->cursor() as $receipt) {
            if ($disk->missing($receipt->original_object_key)) {
                $missing++;
                if (! $this->option('dry-run')) {
                    $receipt->forceFill(['status' => 'failed', 'failed_at' => now(), 'failure_reason' => 'original_object_missing'])->save();
                    AuditEvent::query()->create(['user_id' => $receipt->user_id, 'event_name' => 'receipt.storage_missing', 'aggregate_type' => 'receipt', 'aggregate_id' => $receipt->getKey(), 'summary' => ['receipt_id' => $receipt->getKey()]]);
                }
            }
        }
        $known = Receipt::query()->pluck('original_object_key')->merge(ReceiptDerivative::query()->pluck('object_key'))->flip();
        $orphans = 0;
        foreach ($disk->allFiles('receipts') as $path) {
            if (! $known->has($path)) {
                $orphans++;
                if (! $this->option('dry-run')) {
                    $disk->delete($path);
                }
            }
        }
        $this->info("Missing records: {$missing}; orphan objects: {$orphans}.");

        return self::SUCCESS;
    }
}
