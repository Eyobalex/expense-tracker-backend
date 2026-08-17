<?php

namespace App\Application\Transactions;

use App\Jobs\EvaluateUserNotifications;
use App\Models\FinancialTransaction;
use App\Models\User;

final readonly class PostTransactionService
{
    public function __construct(private TransactionService $transactions) {}

    public function post(User $user, FinancialTransaction $transaction, int $expectedVersion, ?string $requestId = null, ?string $operationId = null): FinancialTransaction
    {
        $posted = $this->transactions->post($user, $transaction, $expectedVersion, $requestId, $operationId);
        EvaluateUserNotifications::dispatch($user->getKey(), $requestId)->afterCommit();

        return $posted;
    }
}
