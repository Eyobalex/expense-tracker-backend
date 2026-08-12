<?php

namespace App\Policies;

use App\Models\FinancialAccount;
use App\Models\User;

class FinancialAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, FinancialAccount $financialAccount): bool
    {
        return $user->id === $financialAccount->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, FinancialAccount $financialAccount): bool
    {
        return $user->id === $financialAccount->user_id;
    }

    public function archive(User $user, FinancialAccount $financialAccount): bool
    {
        return $this->update($user, $financialAccount);
    }

    public function restore(User $user, FinancialAccount $financialAccount): bool
    {
        return $this->update($user, $financialAccount);
    }
}
