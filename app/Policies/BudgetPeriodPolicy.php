<?php

namespace App\Policies;

use App\Models\BudgetPeriod;
use App\Models\User;

class BudgetPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, BudgetPeriod $budgetPeriod): bool
    {
        return $user->id === $budgetPeriod->user_id;
    }
}
