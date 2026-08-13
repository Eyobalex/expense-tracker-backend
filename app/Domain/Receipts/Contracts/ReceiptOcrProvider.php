<?php

namespace App\Domain\Receipts\Contracts;

use App\Domain\Receipts\ValueObjects\OcrResult;

interface ReceiptOcrProvider
{
    public function recognize(string $image, string $mimeType, string $receiptId, string $requestId): OcrResult;
}
