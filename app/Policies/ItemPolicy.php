<?php

namespace App\Policies;

use App\Models\Item;
use App\Models\User;

class ItemPolicy
{
    public function view(User $user, Item $item): bool
    {
        return $user->id === $item->user_id;
    }

    public function update(User $user, Item $item): bool
    {
        return $this->view($user, $item) && $item->merged_into_id === null;
    }
}
