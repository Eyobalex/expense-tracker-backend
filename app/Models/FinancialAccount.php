<?php

namespace App\Models;

use Database\Factories\FinancialAccountFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string $name
 * @property string $type
 * @property string $accounting_type
 * @property string $currency_code
 * @property bool $opening_balance_configured
 * @property Carbon|null $archived_at
 * @property int $version
 */
class FinancialAccount extends Model
{
    /** @use HasFactory<FinancialAccountFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['name', 'type', 'accounting_type', 'currency_code', 'opening_balance_configured', 'archived_at', 'version'];

    protected function casts(): array
    {
        return ['opening_balance_configured' => 'boolean', 'archived_at' => 'immutable_datetime', 'version' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
