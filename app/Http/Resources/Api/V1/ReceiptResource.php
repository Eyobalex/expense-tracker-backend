<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Receipt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Receipt */
class ReceiptResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Receipt $receipt */
        $receipt = $this->resource;
        $latest = $receipt->relationLoaded('extractions') ? $receipt->extractions->sortByDesc('attempt')->first() : null;

        return [
            'id' => $receipt->id, 'status' => $receipt->status, 'mime_type' => $receipt->mime_type, 'byte_size' => $receipt->byte_size,
            'width' => $receipt->width, 'height' => $receipt->height, 'checksum_sha256' => $receipt->checksum_sha256,
            'review_transaction_id' => $receipt->review_transaction_id, 'failure_reason' => $receipt->failure_reason, 'version' => $receipt->version,
            'uploaded_at' => $receipt->uploaded_at?->toISOString(), 'processed_at' => $receipt->processed_at?->toISOString(),
            'extraction' => $latest === null ? null : ['id' => $latest->id, 'status' => $latest->status, 'parser_version' => $latest->parser_version, 'locale' => $latest->locale, 'normalized_data' => $latest->normalized_data, 'confidence' => $latest->confidence, 'failure_reason' => $latest->failure_reason],
        ];
    }
}
