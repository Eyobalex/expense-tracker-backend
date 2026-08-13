<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ReceiptFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property int $user_id
 * @property string $status
 * @property string $original_object_key
 * @property string $mime_type
 * @property string $checksum_sha256
 * @property string|null $review_transaction_id
 * @property CarbonImmutable|null $uploaded_at
 * @property CarbonImmutable|null $processed_at
 * @property CarbonImmutable|null $deleted_at
 */
class Receipt extends Model
{
    /** @use HasFactory<ReceiptFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'immutable_datetime', 'processing_started_at' => 'immutable_datetime', 'processed_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime', 'abandoned_at' => 'immutable_datetime', 'deleted_at' => 'immutable_datetime', 'version' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ReceiptDerivative, $this> */
    public function processingDerivative(): BelongsTo
    {
        return $this->belongsTo(ReceiptDerivative::class, 'processing_derivative_id');
    }

    /** @return BelongsTo<FinancialTransaction, $this> */
    public function reviewTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class, 'review_transaction_id');
    }

    /** @return HasMany<ReceiptDerivative, $this> */
    public function derivatives(): HasMany
    {
        return $this->hasMany(ReceiptDerivative::class);
    }

    /** @return HasMany<ReceiptOcrExtraction, $this> */
    public function extractions(): HasMany
    {
        return $this->hasMany(ReceiptOcrExtraction::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->getKey());
    }
}
