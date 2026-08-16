<?php

namespace App\Policies;

use App\Models\ReportJob;
use App\Models\User;

class ReportJobPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ReportJob $reportJob): bool
    {
        return $reportJob->user_id === $user->getKey();
    }
}
