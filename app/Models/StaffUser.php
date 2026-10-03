<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['uuid', 'name', 'email', 'password', 'mfa_secret', 'mfa_confirmed_at', 'status', 'session_version', 'last_login_at'])]
#[Hidden(['password', 'mfa_secret', 'remember_token'])]
class StaffUser extends User
{
    protected $table = 'staff_users';

    /**
     * @return BelongsToMany<StaffRole, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(StaffRole::class, 'staff_role_assignments')->withPivot(['assigned_by', 'valid_from', 'valid_until'])->withTimestamps();
    }

    public function hasPermission(string $permission): bool
    {
        return $this->roles()->get()->contains(function (StaffRole $role) use ($permission): bool {
            $permissions = $role->getAttribute('permissions');

            return is_array($permissions) && in_array($permission, $permissions, true);
        });
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'mfa_secret' => 'encrypted',
            'mfa_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'session_version' => 'integer',
        ];
    }
}
