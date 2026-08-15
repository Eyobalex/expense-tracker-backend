<?php

namespace App\Models;

use Database\Factories\FxProviderSettingFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FxProviderSetting extends Model
{
    /** @use HasFactory<FxProviderSettingFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'monthly_quota' => 'integer',
            'alert_after_hours' => 'integer',
            'maximum_staleness_hours' => 'integer',
            'is_enabled' => 'boolean',
        ];
    }
}
