<?php

namespace App\Infrastructure\Ocr;

use App\Domain\Receipts\Contracts\ReceiptOcrProvider;
use App\Domain\Receipts\ValueObjects\OcrResult;
use Illuminate\Support\Facades\Http;

final class HttpPaddleOcrProvider implements ReceiptOcrProvider
{
    public function recognize(string $image, string $mimeType, string $receiptId, string $requestId): OcrResult
    {
        $response = Http::baseUrl((string) config('receipts.ocr.url'))
            ->connectTimeout((int) config('receipts.ocr.connect_timeout'))
            ->timeout((int) config('receipts.ocr.timeout'))
            ->acceptJson()
            ->withHeaders(['X-Request-Id' => $requestId, 'X-Receipt-Id' => $receiptId])
            ->attach('image', $image, 'receipt.jpg', ['Content-Type' => $mimeType])
            ->post('/v1/ocr');
        $response->throw();
        $body = $response->json();
        if (! is_array($body)) {
            throw new \RuntimeException('The OCR service returned an invalid response.');
        }

        return new OcrResult(
            rawResponse: $body,
            text: (string) data_get($body, 'text', ''),
            confidence: is_array(data_get($body, 'confidence')) ? data_get($body, 'confidence') : [],
            providerVersion: (string) data_get($body, 'provider_version', config('receipts.ocr.provider_version')),
            modelVersion: (string) data_get($body, 'model_version', config('receipts.ocr.model_version')),
        );
    }
}
