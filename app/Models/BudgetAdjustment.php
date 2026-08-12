<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\BudgetAdjustmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property int $user_id
 * @property string $category_id
 * @property string $budget_period_id
 * @property string $type
 * @property int $amount_minor_units
 * @property string $currency_code
 * @property string|null $paired_adjustment_id
 * @property string|null $source_period_id
 * @property string|null $target_period_id
 * @property string|null $source_transaction_id
 * @property string|null $source_adjustment_id
 * @property int|null $base_limit_snapshot_minor_units
 * @property int|null $actor_user_id
 * @property CarbonImmutable|null $confirmed_at
 * @property string $reason
 * @property array<string, mixed>|null $metadata
 */
class BudgetAdjustment extends Model
{
    /** @use HasFactory<BudgetAdjustmentFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount_minor_units' => 'integer',
            'base_limit_snapshot_minor_units' => 'integer',
            'confirmed_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<BudgetPeriod, $this> */
    public function budgetPeriod(): BelongsTo
    {
        return $this->belongsTo(BudgetPeriod::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<BudgetAdjustment, $this> */
    public function pairedAdjustment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'paired_adjustment_id');
    }
}
