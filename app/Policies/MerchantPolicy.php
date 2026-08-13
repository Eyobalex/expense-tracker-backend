<?php

namespace App\Policies;

use App\Models\Merchant;
use App\Models\User;

class MerchantPolicy
{
    public function view(User $user, Merchant $merchant): bool
    {
        return $user->id === $merchant->user_id;
    }

    public function update(User $user, Merchant $merchant): bool
    {
        return $this->view($user, $merchant) && $merchant->merged_into_id === null;
    }
}
