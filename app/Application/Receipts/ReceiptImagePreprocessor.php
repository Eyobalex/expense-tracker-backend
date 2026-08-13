<?php

namespace App\Application\Receipts;

use App\Models\Receipt;
use App\Models\ReceiptDerivative;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class ReceiptImagePreprocessor
{
    public function createDerivative(Receipt $receipt): ReceiptDerivative
    {
        $existing = $receipt->derivatives()->where('kind', 'ocr_processing')->where('preprocessing_version', config('receipts.preprocessing_version'))->first();
        if ($existing instanceof ReceiptDerivative) {
            return $existing;
        }
        $disk = Storage::disk((string) config('receipts.disk'));
        $source = tempnam(sys_get_temp_dir(), 'receipt-source-');
        $target = tempnam(sys_get_temp_dir(), 'receipt-ocr-');
        if ($source === false || $target === false) {
            throw new \RuntimeException('A temporary receipt file could not be created.');
        }
        try {
            file_put_contents($source, $disk->get($receipt->original_object_key));
            $image = new \Imagick($source);
            $image->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
            $image->stripImage();
            $image->setImageFormat('jpeg');
            $image->setImageCompressionQuality(88);
            if ($image->getImageWidth() > 2400) {
                $image->resizeImage(2400, 0, \Imagick::FILTER_LANCZOS, 1, true);
            }
            $image->writeImage($target);
            $dimensions = getimagesize($target);
            if (! is_array($dimensions)) {
                throw new \RuntimeException('The processing derivative is not a valid image.');
            }
            $id = (string) Str::uuid();
            $key = sprintf('receipts/derivatives/%s/%s.jpg', $receipt->user_id, $id);
            $stream = fopen($target, 'rb');
            if ($stream === false) {
                throw new \RuntimeException('The processing derivative could not be read.');
            }
            $disk->put($key, $stream, ['visibility' => 'private', 'ContentType' => 'image/jpeg']);
            $derivative = $receipt->derivatives()->create([
                'id' => $id, 'kind' => 'ocr_processing', 'object_key' => $key, 'mime_type' => 'image/jpeg', 'byte_size' => filesize($target),
                'width' => (int) $dimensions[0], 'height' => (int) $dimensions[1], 'checksum_sha256' => hash_file('sha256', $target),
                'preprocessing_version' => config('receipts.preprocessing_version'), 'transforms' => ['orientation_normalized', 'metadata_stripped', 'jpeg_normalized', 'max_width_2400'],
            ]);
            $receipt->forceFill(['processing_derivative_id' => $derivative->getKey()])->save();

            return $derivative;
        } finally {
            @unlink($source);
            @unlink($target);
        }
    }
}
