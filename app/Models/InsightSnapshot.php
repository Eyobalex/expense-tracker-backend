<?php

namespace App\Models;

use Database\Factories\InsightSnapshotFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsightSnapshot extends Model
{
    /** @use HasFactory<InsightSnapshotFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'covered_from_at' => 'immutable_datetime',
            'covered_until_at' => 'immutable_datetime',
            'calculated_at' => 'immutable_datetime',
            'formula_version' => 'integer',
            'inputs' => 'array',
            'result' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
