<?php

namespace Database\Factories;

use App\Models\Receipt;
use App\Models\ReceiptOcrExtraction;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ReceiptOcrExtraction> */
class ReceiptOcrExtractionFactory extends Factory
{
    public function definition(): array
    {
        return ['receipt_id' => Receipt::factory(), 'attempt' => 1, 'status' => 'needs_review', 'provider' => 'paddleocr', 'provider_version' => 'fixture', 'model_version' => 'pp-ocrv6', 'parser_version' => 'locale-gated-v1', 'normalized_data' => ['ambiguities' => ['locale_parser_matrix_not_approved']], 'confidence' => []];
    }
}
