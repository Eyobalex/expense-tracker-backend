<?php

namespace App\Models;

use Database\Factories\SyncOperationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncOperation extends Model
{
    /** @use HasFactory<SyncOperationFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['device_id', 'client_operation_id', 'entity', 'action', 'local_id', 'server_id', 'expected_version', 'payload_hash', 'status', 'response_payload', 'error_fields', 'error_code', 'client_occurred_at', 'completed_at'];

    protected function casts(): array
    {
        return ['expected_version' => 'integer', 'response_payload' => 'array', 'error_fields' => 'array', 'client_occurred_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /** @param Builder<self> $query */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->getKey());
    }
}
