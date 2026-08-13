<?php

namespace App\Models;

use Database\Factories\LineItemFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LineItem extends Model
{
    /** @use HasFactory<LineItemFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:6', 'pack_size_value' => 'decimal:6', 'confidence' => 'decimal:6', 'unit_price_minor_units' => 'integer', 'line_total_minor_units' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Receipt, $this> */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    /** @return BelongsTo<FinancialTransaction, $this> */
    public function financialTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class);
    }

    /** @return BelongsTo<Item, $this> */
    public function canonicalItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'canonical_item_id');
    }

    /** @return HasMany<NormalizationCandidate, $this> */
    public function normalizationCandidates(): HasMany
    {
        return $this->hasMany(NormalizationCandidate::class);
    }
}
