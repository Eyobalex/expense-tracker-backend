<?php

namespace App\Policies;

use App\Models\Receipt;
use App\Models\User;

class ReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Receipt $receipt): bool
    {
        return $user->id === $receipt->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Receipt $receipt): bool
    {
        return $this->view($user, $receipt) && in_array($receipt->status, ['needs_review', 'failed'], true);
    }

    public function retry(User $user, Receipt $receipt): bool
    {
        return $this->view($user, $receipt) && in_array($receipt->status, ['needs_review', 'failed'], true);
    }

    public function delete(User $user, Receipt $receipt): bool
    {
        return $this->view($user, $receipt) && $receipt->review_transaction_id === null;
    }
}
