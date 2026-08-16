<?php

use App\Models\ReportJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seedCurrencies();
    Storage::fake('minio');
    config()->set('reports.disk', 'minio');
});

test('expired report cleanup deletes the private artifact and marks the job expired idempotently', function (): void {
    $user = User::factory()->create(['base_currency_code' => 'ETB']);
    $key = 'reports/'.$user->uuid.'/expired.csv';
    Storage::disk('minio')->put($key, 'expired');
    $report = ReportJob::factory()->for($user)->create(['base_currency_code' => 'ETB', 'status' => 'completed', 'progress' => 100, 'private_object_key' => $key, 'expires_at' => now()->subMinute()]);

    $this->artisan('reports:cleanup')->assertSuccessful();
    expect($report->fresh()->status)->toBe('expired')
        ->and($report->fresh()->private_object_key)->toBeNull();
    Storage::disk('minio')->assertMissing($key);

    $this->artisan('reports:cleanup')->assertSuccessful();
    expect($report->fresh()->status)->toBe('expired');
});
