<?php

namespace App\Models;

use App\Domain\Currency\ValueObjects\CurrencyCode;
use App\Domain\Currency\ValueObjects\CurrencyMetadata;
use Database\Factories\CurrencyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    /** @use HasFactory<CurrencyFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $primaryKey = 'code';

    protected $fillable = ['code', 'display_name', 'symbol', 'minor_unit_exponent', 'is_active'];

    protected function casts(): array
    {
        return ['minor_unit_exponent' => 'integer', 'is_active' => 'boolean'];
    }

    public function metadata(): CurrencyMetadata
    {
        return new CurrencyMetadata(
            CurrencyCode::fromString($this->code),
            $this->minor_unit_exponent,
            $this->display_name,
            $this->symbol,
        );
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
