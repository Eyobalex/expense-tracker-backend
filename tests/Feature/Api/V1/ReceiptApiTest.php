<?php

use App\Application\Receipts\ReceiptProcessingService;
use App\Domain\Receipts\Contracts\ReceiptOcrProvider;
use App\Domain\Receipts\ValueObjects\OcrResult;
use App\Jobs\ProcessReceiptOcr;
use App\Models\FinancialAccount;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seedCurrencies();
    Storage::fake('minio');
    Queue::fake();
});

function receiptToken(User $user): string
{
    return $user->createToken('receipt-test', ['api'])->plainTextToken;
}

function receiptHeaders(User $user): array
{
    return ['Accept' => 'application/json', 'Idempotency-Key' => (string) Str::uuid(), 'Authorization' => 'Bearer '.receiptToken($user)];
}

test('an owner uploads a validated immutable private receipt and OCR work is queued', function (): void {
    $user = User::factory()->create(['base_currency_code' => 'ETB']);
    $upload = UploadedFile::fake()->image('../untrusted-name.jpg', 100, 120)->size(12);

    $this->postJson('/api/v1/receipts', ['receipt' => $upload], receiptHeaders($user))
        ->assertCreated()->assertJsonPath('data.status', 'uploaded')->assertJsonPath('data.mime_type', 'image/jpeg');

    $receipt = Receipt::query()->sole();
    expect($receipt->original_object_key)->toStartWith('receipts/originals/')
        ->and($receipt->original_object_key)->not->toContain('untrusted-name')
        ->and($receipt->original_filename)->toBe('untrusted-name.jpg');
    Storage::disk('minio')->assertExists($receipt->original_object_key);
    Queue::assertPushed(ProcessReceiptOcr::class, fn (ProcessReceiptOcr $job): bool => $job->receiptId === $receipt->id);
});

test('upload rejects non-image content and a different user cannot access receipt metadata or media', function (): void {
    $owner = User::factory()->create(['base_currency_code' => 'ETB']);
    $other = User::factory()->create(['base_currency_code' => 'ETB']);
    $this->postJson('/api/v1/receipts', ['receipt' => UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf')], receiptHeaders($owner))
        ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED');

    $receipt = Receipt::factory()->for($owner)->create();
    Storage::disk('minio')->put($receipt->original_object_key, 'receipt');
    $this->getJson("/api/v1/receipts/{$receipt->id}", ['Accept' => 'application/json', 'Authorization' => 'Bearer '.receiptToken($other)])->assertNotFound();
    $this->getJson("/api/v1/receipts/{$receipt->id}/download", ['Accept' => 'application/json', 'Authorization' => 'Bearer '.receiptToken($other)])->assertNotFound();
});

test('OCR persists raw and locale-gated normalized results without creating a financial transaction', function (): void {
    Queue::fake();
    $user = User::factory()->create(['base_currency_code' => 'ETB']);
    $receipt = Receipt::factory()->for($user)->create(['status' => 'uploaded']);
    Storage::disk('minio')->put($receipt->original_object_key, UploadedFile::fake()->image('receipt.jpg', 100, 100)->getContent());
    app()->instance(ReceiptOcrProvider::class, new class implements ReceiptOcrProvider
    {
        public function recognize(string $image, string $mimeType, string $receiptId, string $requestId): OcrResult
        {
            return new OcrResult(['text' => '1.250,50 11/08/2026'], '1.250,50 11/08/2026', ['overall' => 0.91], 'fixture-provider', 'pp-ocrv6-fixture');
        }
    });

    app(ReceiptProcessingService::class)->process($receipt->id, (string) Str::uuid(), 'fixture-job');

    $receipt->refresh()->load('extractions');
    expect($receipt->status)->toBe('needs_review')
        ->and($receipt->derivatives)->toHaveCount(1)
        ->and($receipt->extractions)->toHaveCount(1)
        ->and($receipt->extractions->first()->normalized_data['ambiguities'])->toContain('locale_parser_matrix_not_approved')
        ->and($receipt->review_transaction_id)->toBeNull();
    $this->assertDatabaseCount('financial_transactions', 0);
});

test('review creates one ordinary pending-review transaction and OCR cannot post it automatically', function (): void {
    $user = User::factory()->create(['base_currency_code' => 'ETB']);
    $account = FinancialAccount::factory()->for($user)->create(['currency_code' => 'ETB']);
    $receipt = Receipt::factory()->for($user)->create(['status' => 'needs_review']);
    $payload = [
        'financial_account_id' => $account->id, 'type' => 'expense', 'occurred_at' => '2026-08-13T10:00:00Z',
        'occurred_timezone' => 'UTC', 'original_amount_minor_units' => 1250, 'original_currency_code' => 'ETB', 'description' => 'Reviewed OCR receipt',
    ];

    $this->postJson("/api/v1/receipts/{$receipt->id}/review-transaction", $payload, receiptHeaders($user))
        ->assertCreated()->assertJsonPath('data.state', 'pending_review')->assertJsonPath('data.source', 'receipt_ocr');
    $this->postJson("/api/v1/receipts/{$receipt->id}/review-transaction", $payload, receiptHeaders($user))->assertUnprocessable();

    $receipt->refresh();
    expect($receipt->review_transaction_id)->not->toBeNull();
    $this->assertDatabaseCount('journal_entries', 0);
});

test('a failed OCR receipt can be retried by its owner and retry is queued', function (): void {
    $user = User::factory()->create(['base_currency_code' => 'ETB']);
    $receipt = Receipt::factory()->for($user)->create(['status' => 'failed', 'failed_at' => now(), 'failure_reason' => 'ocr_processing_failed']);

    $this->postJson("/api/v1/receipts/{$receipt->id}/retry", [], receiptHeaders($user))->assertAccepted()->assertJsonPath('data.status', 'uploaded');
    Queue::assertPushed(ProcessReceiptOcr::class);
});
