<?php

namespace App\Policies;

use App\Models\FinancialTransaction;
use App\Models\User;

class FinancialTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, FinancialTransaction $transaction): bool
    {
        return $user->id === $transaction->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, FinancialTransaction $transaction): bool
    {
        return $this->view($user, $transaction) && in_array($transaction->state, ['draft', 'pending_review'], true);
    }

    public function delete(User $user, FinancialTransaction $transaction): bool
    {
        return $this->update($user, $transaction);
    }

    public function post(User $user, FinancialTransaction $transaction): bool
    {
        return $this->update($user, $transaction);
    }

    public function reverse(User $user, FinancialTransaction $transaction): bool
    {
        return $this->view($user, $transaction) && $transaction->state === 'posted';
    }

    public function correct(User $user, FinancialTransaction $transaction): bool
    {
        return $this->reverse($user, $transaction);
    }
}
