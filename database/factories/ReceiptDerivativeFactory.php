<?php

namespace Database\Factories;

use App\Models\Receipt;
use App\Models\ReceiptDerivative;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ReceiptDerivative> */
class ReceiptDerivativeFactory extends Factory
{
    public function definition(): array
    {
        $id = (string) Str::uuid();

        return ['id' => $id, 'receipt_id' => Receipt::factory(), 'kind' => 'ocr_processing', 'object_key' => 'receipts/derivatives/test/'.$id.'.jpg', 'mime_type' => 'image/jpeg', 'byte_size' => 800, 'width' => 100, 'height' => 100, 'checksum_sha256' => hash('sha256', $id), 'preprocessing_version' => 'v1', 'transforms' => ['orientation_normalized']];
    }
}
