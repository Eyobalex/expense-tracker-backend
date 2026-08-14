<?php

namespace App\Http\Resources\Api\V1;

use App\Models\TransactionDuplicateCandidate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionDuplicateCandidateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TransactionDuplicateCandidate $candidate */
        $candidate = $this->resource;
        $transaction = $candidate->candidateTransaction;

        return [
            'id' => $candidate->id,
            'transaction_id' => $candidate->financial_transaction_id,
            'candidate_transaction_id' => $candidate->candidate_transaction_id,
            'score' => $candidate->score,
            'score_breakdown' => $candidate->score_breakdown,
            'algorithm_version' => $candidate->algorithm_version,
            'status' => $candidate->status,
            'decided_at' => $candidate->decided_at?->toISOString(),
            'candidate_transaction' => $transaction === null ? null : [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'state' => $transaction->state,
                'financial_account_id' => $transaction->financial_account_id,
                'original_amount_minor_units' => $transaction->original_amount_minor_units,
                'original_currency_code' => $transaction->original_currency_code,
                'occurred_at' => $transaction->occurred_at->toISOString(),
                'reference_number' => $transaction->reference_number,
            ],
        ];
    }
}
