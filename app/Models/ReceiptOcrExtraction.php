<?php

namespace App\Models;

use Database\Factories\ReceiptOcrExtractionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptOcrExtraction extends Model
{
    /** @use HasFactory<ReceiptOcrExtractionFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['raw_response' => 'array', 'normalized_data' => 'array', 'confidence' => 'array', 'started_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Receipt, $this> */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    /** @return BelongsTo<ReceiptDerivative, $this> */
    public function derivative(): BelongsTo
    {
        return $this->belongsTo(ReceiptDerivative::class, 'receipt_derivative_id');
    }
}
