<?php

namespace App\Models;

use Database\Factories\StaffRoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'permissions'])]
class StaffRole extends Model
{
    /** @use HasFactory<StaffRoleFactory> */
    use HasFactory;

    /**
     * @return BelongsToMany<StaffUser, $this>
     */
    public function staffUsers(): BelongsToMany
    {
        return $this->belongsToMany(StaffUser::class, 'staff_role_assignments')->withPivot(['assigned_by', 'valid_from', 'valid_until'])->withTimestamps();
    }

    protected function casts(): array
    {
        return ['permissions' => 'array'];
    }
}
