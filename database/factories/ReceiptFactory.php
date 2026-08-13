<?php

namespace Database\Factories;

use App\Models\Receipt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Receipt> */
class ReceiptFactory extends Factory
{
    public function definition(): array
    {
        $id = (string) Str::uuid();

        return [
            'id' => $id, 'user_id' => User::factory(), 'status' => 'needs_review', 'original_object_key' => 'receipts/originals/test/'.$id.'.jpg',
            'original_filename' => 'receipt.jpg', 'mime_type' => 'image/jpeg', 'extension' => 'jpg', 'byte_size' => 1024,
            'width' => 100, 'height' => 100, 'checksum_sha256' => hash('sha256', $id), 'uploaded_at' => now(),
        ];
    }
}
