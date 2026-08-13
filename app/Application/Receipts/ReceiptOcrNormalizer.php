<?php

namespace App\Application\Receipts;

use App\Domain\Receipts\ValueObjects\OcrResult;

final class ReceiptOcrNormalizer
{
    /** @return array<string, mixed> */
    public function normalize(OcrResult $result): array
    {
        return [
            'raw_text' => $result->text,
            'receipt_type' => 'unknown',
            'status' => 'needs_review',
            'ambiguities' => ['locale_parser_matrix_not_approved'],
            'parsed_fields' => (object) [],
        ];
    }
}
