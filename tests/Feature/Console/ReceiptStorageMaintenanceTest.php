<?php

use App\Models\Receipt;
use App\Models\ReceiptDerivative;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('minio');
});

test('reconciliation marks records whose private original object is missing and removes orphans', function (): void {
    $receipt = Receipt::factory()->for(User::factory())->create(['status' => 'needs_review']);
    Storage::disk('minio')->put('receipts/originals/orphan.jpg', 'orphan');

    $this->artisan('receipt:reconcile-storage')->assertSuccessful();

    expect($receipt->fresh()->status)->toBe('failed')->and($receipt->fresh()->failure_reason)->toBe('original_object_missing');
    Storage::disk('minio')->assertMissing('receipts/originals/orphan.jpg');
});

test('cleanup removes expired abandoned original and derivative objects while preserving records for audit', function (): void {
    $receipt = Receipt::factory()->for(User::factory())->create(['status' => 'abandoned', 'abandoned_at' => now()->subDays(31)]);
    $derivative = ReceiptDerivative::factory()->for($receipt)->create();
    Storage::disk('minio')->put($receipt->original_object_key, 'original');
    Storage::disk('minio')->put($derivative->object_key, 'derivative');

    $this->artisan('receipt:cleanup-artifacts')->assertSuccessful();

    expect($receipt->fresh()->status)->toBe('deleted')->and($receipt->fresh()->deleted_at)->not->toBeNull();
    Storage::disk('minio')->assertMissing($receipt->original_object_key);
    Storage::disk('minio')->assertMissing($derivative->object_key);
});
