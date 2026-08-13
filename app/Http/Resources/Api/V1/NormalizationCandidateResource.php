<?php

namespace App\Http\Resources\Api\V1;

use App\Models\NormalizationCandidate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin NormalizationCandidate */
class NormalizationCandidateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var NormalizationCandidate $candidate */
        $candidate = $this->resource;

        return ['id' => $candidate->id, 'receipt_id' => $candidate->receipt_id, 'line_item_id' => $candidate->line_item_id, 'entity_type' => $candidate->entity_type, 'candidate_merchant_id' => $candidate->candidate_merchant_id, 'candidate_item_id' => $candidate->candidate_item_id, 'raw_value' => $candidate->raw_value, 'score' => $candidate->score, 'normalization_version' => $candidate->normalization_version, 'status' => $candidate->status, 'resolved_at' => $candidate->resolved_at];
    }
}
