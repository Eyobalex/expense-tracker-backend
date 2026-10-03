<?php

namespace App\Models;

use Database\Factories\AdminAuditEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['staff_user_id', 'action', 'outcome', 'context', 'request_id', 'created_at'])]
class AdminAuditEvent extends Model
{
    /** @use HasFactory<AdminAuditEventFactory> */
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['context' => 'array', 'created_at' => 'immutable_datetime'];
    }
}
