<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ExchangeRateFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $base_currency_code
 * @property string $quote_currency_code
 * @property CarbonImmutable $rate_date
 * @property string $rate
 * @property string $provider
 * @property CarbonImmutable|null $provider_published_at
 * @property CarbonImmutable $retrieved_at
 */
class ExchangeRate extends Model
{
    /** @use HasFactory<ExchangeRateFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rate_date' => 'immutable_date',
            'rate' => 'decimal:18',
            'provider_published_at' => 'immutable_datetime',
            'retrieved_at' => 'immutable_datetime',
            'provider_metadata' => 'array',
        ];
    }
}
