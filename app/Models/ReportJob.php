<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ReportJobFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property int $user_id
 * @property string $type
 * @property string $format
 * @property array<string, mixed> $filters
 * @property string $base_currency_code
 * @property string $timezone
 * @property string $status
 * @property int $progress
 * @property string|null $private_object_key
 * @property string|null $artifact_filename
 * @property string|null $mime_type
 * @property int|null $byte_size
 * @property string|null $checksum_sha256
 * @property string|null $error_code
 * @property string|null $request_id
 * @property string|null $job_id
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $deleted_at
 */
class ReportJob extends Model
{
    /** @use HasFactory<ReportJobFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'type', 'format', 'filters', 'base_currency_code', 'timezone', 'status', 'progress', 'private_object_key',
        'artifact_filename', 'mime_type', 'byte_size', 'checksum_sha256', 'error_code', 'request_id', 'job_id',
        'started_at', 'completed_at', 'expires_at', 'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array', 'progress' => 'integer', 'byte_size' => 'integer', 'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'deleted_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->getKey());
    }
}
