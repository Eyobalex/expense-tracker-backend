<?php

namespace App\Application\Receipts;

use App\Application\Notifications\NotificationService;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Receipts\Contracts\ReceiptOcrProvider;
use App\Jobs\NormalizeReceiptEntities;
use App\Jobs\ProcessReceiptOcr;
use App\Models\AuditEvent;
use App\Models\Receipt;
use App\Models\ReceiptDerivative;
use App\Models\ReceiptOcrExtraction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final readonly class ReceiptProcessingService
{
    public function __construct(
        private ReceiptImagePreprocessor $preprocessor,
        private ReceiptOcrProvider $ocr,
        private ReceiptOcrNormalizer $normalizer,
        private NotificationService $notifications,
    ) {}

    public function queue(Receipt $receipt, ?string $requestId = null): void
    {
        ProcessReceiptOcr::dispatch($receipt->getKey(), $requestId ?? $receipt->request_id ?? (string) Str::uuid())->onQueue('ocr')->afterCommit();
    }

    public function process(string $receiptId, string $requestId, ?string $jobId = null): void
    {
        $prepared = DB::transaction(function () use ($receiptId, $requestId, $jobId): ?array {
            /** @var Receipt|null $receipt */
            $receipt = Receipt::query()->whereKey($receiptId)->lockForUpdate()->first();
            if (! $receipt instanceof Receipt || in_array($receipt->status, ['needs_review', 'deleted', 'abandoned'], true)) {
                return null;
            }
            $receipt->forceFill(['status' => 'processing', 'processing_started_at' => now(), 'failure_reason' => null])->save();
            $derivative = $this->preprocessor->createDerivative($receipt);
            $attempt = (int) $receipt->extractions()->max('attempt') + 1;
            /** @var ReceiptOcrExtraction $extraction */
            $extraction = $receipt->extractions()->create([
                'receipt_derivative_id' => $derivative->getKey(), 'attempt' => $attempt, 'status' => 'processing',
                'provider' => 'paddleocr', 'provider_version' => config('receipts.ocr.provider_version'), 'model_version' => config('receipts.ocr.model_version'),
                'parser_version' => config('receipts.parser.version'), 'request_id' => $requestId, 'job_id' => $jobId, 'started_at' => now(),
            ]);

            return [$receipt->getKey(), $derivative->getKey(), $extraction->getKey()];
        }, attempts: 3);
        if ($prepared === null) {
            return;
        }
        [$lockedReceiptId, $derivativeId, $extractionId] = $prepared;
        /** @var ReceiptDerivative $derivative */
        $derivative = ReceiptDerivative::query()->findOrFail($derivativeId);
        $image = Storage::disk((string) config('receipts.disk'))->get($derivative->object_key);
        $result = $this->ocr->recognize($image, $derivative->mime_type, $lockedReceiptId, $requestId);

        $normalizedReceiptId = DB::transaction(function () use ($lockedReceiptId, $extractionId, $result, $requestId, $jobId): ?string {
            /** @var Receipt|null $receipt */
            $receipt = Receipt::query()->whereKey($lockedReceiptId)->lockForUpdate()->first();
            /** @var ReceiptOcrExtraction|null $extraction */
            $extraction = ReceiptOcrExtraction::query()->whereKey($extractionId)->lockForUpdate()->first();
            if (! $receipt instanceof Receipt || ! $extraction instanceof ReceiptOcrExtraction || $extraction->status !== 'processing') {
                return null;
            }
            $normalized = $this->normalizer->normalize($result);
            $extraction->forceFill([
                'status' => 'needs_review', 'provider_version' => $result->providerVersion, 'model_version' => $result->modelVersion,
                'locale' => $normalized['locale'], 'raw_response' => $result->rawResponse, 'normalized_data' => $normalized, 'confidence' => $result->confidence, 'completed_at' => now(),
            ])->save();
            $receipt->forceFill(['status' => 'needs_review', 'processed_at' => now()])->save();
            AuditEvent::query()->create(['user_id' => $receipt->user_id, 'event_name' => 'receipt.ocr_completed', 'aggregate_type' => 'receipt', 'aggregate_id' => $receipt->getKey(), 'request_id' => $requestId, 'summary' => ['receipt_id' => $receipt->getKey(), 'extraction_id' => $extraction->getKey(), 'job_id' => $jobId]]);

            return $receipt->getKey();
        }, attempts: 3);
        if ($normalizedReceiptId !== null) {
            NormalizeReceiptEntities::dispatch($normalizedReceiptId)->onQueue('normalization')->afterCommit();
        }
    }

    public function failed(string $receiptId, string $requestId, ?string $jobId, \Throwable $exception): void
    {
        Receipt::query()->whereKey($receiptId)->update(['status' => 'failed', 'failed_at' => now(), 'failure_reason' => 'ocr_processing_failed']);
        $receipt = Receipt::query()->find($receiptId);
        if ($receipt instanceof Receipt) {
            AuditEvent::query()->create(['user_id' => $receipt->user_id, 'event_name' => 'receipt.ocr_failed', 'aggregate_type' => 'receipt', 'aggregate_id' => $receiptId, 'request_id' => $requestId, 'summary' => ['receipt_id' => $receiptId, 'job_id' => $jobId, 'exception' => $exception::class]]);
            $this->notifications->create(
                $receipt->user,
                NotificationType::OcrFailed,
                'ocr-failed:'.$receipt->getKey(),
                'Receipt processing needs review',
                'Receipt processing did not complete. Your original receipt remains available for review or retry.',
                ['receipt_id' => $receipt->getKey(), 'rule' => ['name' => 'ocr_failure', 'version' => 1]],
                requestId: $requestId,
            );
        }
    }
}
