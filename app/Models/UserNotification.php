<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\UserNotificationFactory;
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
 * @property string $title
 * @property string $body
 * @property array<string, mixed> $payload
 * @property bool $in_app_enabled
 * @property int $version
 * @property CarbonImmutable|null $read_at
 * @property CarbonImmutable|null $created_at
 */
class UserNotification extends Model
{
    /** @use HasFactory<UserNotificationFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'type',
        'title',
        'body',
        'payload',
        'in_app_enabled',
        'insight_snapshot_id',
        'deduplication_key',
        'read_at',
        'request_id',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'in_app_enabled' => 'boolean',
            'read_at' => 'immutable_datetime',
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<InsightSnapshot, $this> */
    public function insightSnapshot(): BelongsTo
    {
        return $this->belongsTo(InsightSnapshot::class);
    }

    /** @return HasMany<NotificationDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
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
