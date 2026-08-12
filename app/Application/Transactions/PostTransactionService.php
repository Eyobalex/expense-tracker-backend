<?php

namespace App\Application\Transactions;

use App\Models\FinancialTransaction;
use App\Models\User;

final readonly class PostTransactionService
{
    public function __construct(private TransactionService $transactions) {}

    public function post(User $user, FinancialTransaction $transaction, int $expectedVersion): FinancialTransaction
    {
        return $this->transactions->post($user, $transaction, $expectedVersion);
    }
}
