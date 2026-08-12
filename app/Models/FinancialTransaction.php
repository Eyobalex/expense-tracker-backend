<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\FinancialTransactionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property int $user_id
 * @property string $type
 * @property string $state
 * @property string $financial_account_id
 * @property string|null $counterparty_account_id
 * @property string|null $category_id
 * @property string|null $related_transaction_id
 * @property string|null $correction_of_id
 * @property int $original_amount_minor_units
 * @property string $original_currency_code
 * @property int|null $base_amount_minor_units
 * @property string|null $base_currency_code
 * @property string|null $used_rate
 * @property int $version
 * @property CarbonImmutable $occurred_at
 * @property CarbonImmutable|null $rate_date
 * @property CarbonImmutable|null $posted_at
 * @property CarbonImmutable|null $reversed_at
 */
class FinancialTransaction extends Model
{
    /** @use HasFactory<FinancialTransactionFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'financial_account_id', 'counterparty_account_id', 'category_id', 'related_transaction_id', 'correction_of_id', 'type', 'state', 'source',
        'adjustment_subtype', 'adjustment_direction', 'reason', 'occurred_at', 'occurred_timezone', 'original_amount_minor_units',
        'original_currency_code', 'counterparty_amount_minor_units', 'counterparty_currency_code', 'reference_rate', 'used_rate', 'rate_date',
        'rate_source', 'rate_override_reason', 'rounding_mode', 'description', 'version',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime', 'rate_date' => 'immutable_date', 'posted_at' => 'immutable_datetime',
            'reversed_at' => 'immutable_datetime', 'version' => 'integer', 'original_amount_minor_units' => 'integer',
            'counterparty_amount_minor_units' => 'integer', 'base_amount_minor_units' => 'integer', 'reference_rate' => 'decimal:18', 'used_rate' => 'decimal:18',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<FinancialAccount, $this> */
    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    /** @return BelongsTo<FinancialAccount, $this> */
    public function counterpartyAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'counterparty_account_id');
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /** @return BelongsTo<FinancialTransaction, $this> */
    public function relatedTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_transaction_id');
    }

    /** @return HasMany<TransactionSplit, $this> */
    public function splits(): HasMany
    {
        return $this->hasMany(TransactionSplit::class);
    }

    /**
     * @param  Builder<FinancialTransaction>  $query
     * @return Builder<FinancialTransaction>
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->getKey());
    }
}
