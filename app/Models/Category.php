<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string|null $parent_id
 * @property string $name
 * @property string $kind
 * @property bool $is_active
 * @property bool $is_system
 * @property bool $budget_enabled
 * @property int|null $base_limit_minor_units
 * @property bool $rollover_enabled
 * @property bool $overspend_carry_enabled
 * @property bool $borrowing_enabled
 * @property Carbon|null $archived_at
 * @property int $version
 */
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['parent_id', 'name', 'kind', 'is_active', 'budget_enabled', 'base_limit_minor_units', 'rollover_enabled', 'overspend_carry_enabled', 'borrowing_enabled', 'archived_at', 'version'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_system' => 'boolean', 'budget_enabled' => 'boolean', 'rollover_enabled' => 'boolean', 'overspend_carry_enabled' => 'boolean', 'borrowing_enabled' => 'boolean', 'archived_at' => 'immutable_datetime', 'version' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Category, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @param Builder<self> $query @return Builder<self> */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->getKey());
    }
}
