<?php

namespace App\Models;

use Database\Factories\LedgerAccountMappingFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LedgerAccountMapping extends Model
{
    /** @use HasFactory<LedgerAccountMappingFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['user_id', 'entity_type', 'entity_id', 'mapping_key', 'ledger_code'];
}
