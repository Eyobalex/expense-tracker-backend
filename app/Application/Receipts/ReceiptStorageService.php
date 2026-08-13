<?php

namespace App\Application\Receipts;

use App\Domain\Shared\Exceptions\DomainErrorCode;
use App\Domain\Shared\Exceptions\DomainException;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class ReceiptStorageService
{
    public function store(User $user, UploadedFile $file, string $requestId): Receipt
    {
        $metadata = $this->validatedMetadata($file);
        $existing = Receipt::query()->ownedBy($user)->where('checksum_sha256', $metadata['checksum_sha256'])->whereNull('deleted_at')->first();
        if ($existing instanceof Receipt) {
            return $existing;
        }

        $id = (string) Str::uuid();
        $key = sprintf('receipts/originals/%s/%s.%s', $user->uuid ?? $user->id, $id, $metadata['extension']);
        $disk = Storage::disk((string) config('receipts.disk'));
        $stored = false;

        try {
            $stream = fopen($file->getRealPath(), 'rb');
            if ($stream === false || ! $disk->put($key, $stream, ['visibility' => 'private', 'ContentType' => $metadata['mime_type']])) {
                throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'The receipt could not be stored privately.');
            }
            $stored = true;

            return DB::transaction(function () use ($user, $metadata, $requestId, $id, $key): Receipt {
                return Receipt::query()->create([
                    'id' => $id, 'user_id' => $user->getKey(), 'status' => 'uploaded', 'original_object_key' => $key,
                    'original_filename' => $this->safeFilename($metadata['client_filename']), 'mime_type' => $metadata['mime_type'],
                    'extension' => $metadata['extension'], 'byte_size' => $metadata['byte_size'], 'width' => $metadata['width'],
                    'height' => $metadata['height'], 'checksum_sha256' => $metadata['checksum_sha256'], 'request_id' => $requestId, 'uploaded_at' => now(),
                ]);
            }, attempts: 3);
        } catch (\Throwable $exception) {
            if ($stored) {
                $disk->delete($key);
            }

            throw $exception;
        }
    }

    /** @return array{mime_type:string,extension:string,byte_size:int,width:int,height:int,checksum_sha256:string,client_filename:string} */
    private function validatedMetadata(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        $size = $file->getSize();
        if (! is_string($path) || ! is_int($size) || $size < 1 || $size > config('receipts.max_bytes')) {
            throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'The receipt file size is not permitted.');
        }
        $imageInfo = @getimagesize($path);
        $imageType = @exif_imagetype($path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $map = [IMAGETYPE_JPEG => ['image/jpeg', 'jpg'], IMAGETYPE_PNG => ['image/png', 'png'], IMAGETYPE_WEBP => ['image/webp', 'webp']];
        if (! is_array($imageInfo) || ! is_int($imageType) || ! isset($map[$imageType]) || $mime !== $map[$imageType][0] || ! in_array($mime, config('receipts.allowed_mime_types'), true)) {
            throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'The receipt must be a valid JPEG, PNG, or WebP image.');
        }
        [$width, $height] = [(int) $imageInfo[0], (int) $imageInfo[1]];
        if ($width < 1 || $height < 1 || $width * $height > config('receipts.max_pixels')) {
            throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'The receipt image dimensions are not permitted.');
        }

        $checksum = hash_file('sha256', $path);
        if ($checksum === false) {
            throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'The receipt checksum could not be calculated.');
        }

        return ['mime_type' => $mime, 'extension' => $map[$imageType][1], 'byte_size' => $size, 'width' => $width, 'height' => $height, 'checksum_sha256' => $checksum, 'client_filename' => (string) $file->getClientOriginalName()];
    }

    private function safeFilename(string $filename): string
    {
        return Str::limit(basename(str_replace('\\', '/', $filename)), 255, '');
    }
}
