<?php

namespace App\Policies;

use App\Models\NormalizationCandidate;
use App\Models\User;

class NormalizationCandidatePolicy
{
    public function view(User $user, NormalizationCandidate $candidate): bool
    {
        return $user->id === $candidate->user_id;
    }

    public function resolve(User $user, NormalizationCandidate $candidate): bool
    {
        return $this->view($user, $candidate) && $candidate->status === 'suggested';
    }
}
