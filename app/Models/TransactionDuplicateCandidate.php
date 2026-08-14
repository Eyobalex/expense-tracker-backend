<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TransactionDuplicateCandidateFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property int $user_id
 * @property string $financial_transaction_id
 * @property string $candidate_transaction_id
 * @property string $score
 * @property array<string, float> $score_breakdown
 * @property string $algorithm_version
 * @property string $status
 * @property int|null $decided_by_user_id
 * @property CarbonImmutable|null $decided_at
 */
class TransactionDuplicateCandidate extends Model
{
    /** @use HasFactory<TransactionDuplicateCandidateFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:6',
            'score_breakdown' => 'array',
            'decided_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<FinancialTransaction, $this> */
    public function financialTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class);
    }

    /** @return BelongsTo<FinancialTransaction, $this> */
    public function candidateTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class, 'candidate_transaction_id');
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }
}
