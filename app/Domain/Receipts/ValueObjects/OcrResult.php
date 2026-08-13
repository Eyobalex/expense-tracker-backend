<?php

namespace App\Domain\Receipts\ValueObjects;

final readonly class OcrResult
{
    /**
     * @param  array<string, mixed>  $rawResponse
     * @param  array<string, float>  $confidence
     */
    public function __construct(
        public array $rawResponse,
        public string $text,
        public array $confidence,
        public string $providerVersion,
        public string $modelVersion,
    ) {}
}
