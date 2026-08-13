<?php

namespace App\Policies;

use App\Models\Device;
use App\Models\User;

class DevicePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Device $device): bool
    {
        return $device->user_id === $user->getKey();
    }

    public function delete(User $user, Device $device): bool
    {
        return $this->view($user, $device);
    }
}
