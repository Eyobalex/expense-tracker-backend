<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\NotificationDeliveryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $user_notification_id
 * @property string|null $device_id
 * @property string $channel
 * @property string $status
 * @property CarbonImmutable|null $delivered_at
 */
class NotificationDelivery extends Model
{
    /** @use HasFactory<NotificationDeliveryFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'device_id',
        'channel',
        'status',
        'idempotency_key',
        'attempt_count',
        'job_id',
        'dispatched_at',
        'delivered_at',
        'failed_at',
        'failure_code',
    ];

    protected function casts(): array
    {
        return [
            'attempt_count' => 'integer',
            'dispatched_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<UserNotification, $this> */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(UserNotification::class, 'user_notification_id');
    }

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
