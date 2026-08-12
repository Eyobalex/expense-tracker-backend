<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property int $user_id
 * @property string $client_device_id
 * @property string $platform
 * @property string|null $app_version
 * @property int|null $personal_access_token_id
 * @property CarbonImmutable $last_seen_at
 * @property CarbonImmutable|null $revoked_at
 */
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['client_device_id', 'platform', 'app_version', 'last_seen_at', 'revoked_at', 'personal_access_token_id'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
