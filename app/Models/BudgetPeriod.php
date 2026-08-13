<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\BudgetPeriodFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property int $user_id
 * @property string $category_id
 * @property int $period_year
 * @property int $period_month
 * @property string $budget_timezone
 * @property CarbonImmutable $period_start_at
 * @property CarbonImmutable $period_end_at
 * @property string $status
 * @property string $currency_code
 * @property int $base_limit_minor_units
 * @property int $borrowing_deduction_minor_units
 * @property int $positive_rollover_minor_units
 * @property int $negative_carry_minor_units
 * @property int $reallocation_in_minor_units
 * @property int $reallocation_out_minor_units
 * @property int $effective_limit_minor_units
 * @property int $actual_spent_minor_units
 * @property int $remaining_minor_units
 * @property CarbonImmutable $initialized_at
 * @property CarbonImmutable|null $closed_at
 * @property int $version
 */
class BudgetPeriod extends Model
{
    /** @use HasFactory<BudgetPeriodFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'period_start_at' => 'immutable_datetime',
            'period_end_at' => 'immutable_datetime',
            'initialized_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'period_year' => 'integer',
            'period_month' => 'integer',
            'base_limit_minor_units' => 'integer',
            'rollover_enabled_snapshot' => 'boolean',
            'overspend_carry_enabled_snapshot' => 'boolean',
            'borrowing_enabled_snapshot' => 'boolean',
            'borrowing_deduction_minor_units' => 'integer',
            'positive_rollover_minor_units' => 'integer',
            'negative_carry_minor_units' => 'integer',
            'reallocation_in_minor_units' => 'integer',
            'reallocation_out_minor_units' => 'integer',
            'effective_limit_minor_units' => 'integer',
            'actual_spent_minor_units' => 'integer',
            'remaining_minor_units' => 'integer',
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<BudgetAdjustment, $this> */
    public function adjustments(): HasMany
    {
        return $this->hasMany(BudgetAdjustment::class);
    }

    /**
     * @param  Builder<BudgetPeriod>  $query
     * @return Builder<BudgetPeriod>
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->getKey());
    }
}
