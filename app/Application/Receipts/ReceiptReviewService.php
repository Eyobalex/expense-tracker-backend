<?php

namespace App\Application\Receipts;

use App\Application\Transactions\TransactionService;
use App\Domain\Shared\Exceptions\DomainErrorCode;
use App\Domain\Shared\Exceptions\DomainException;
use App\Models\AuditEvent;
use App\Models\FinancialTransaction;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class ReceiptReviewService
{
    public function __construct(private TransactionService $transactions) {}

    /** @param array<string, mixed> $attributes */
    public function createTransaction(User $user, Receipt $receipt, array $attributes): FinancialTransaction
    {
        return DB::transaction(function () use ($user, $receipt, $attributes): FinancialTransaction {
            /** @var Receipt|null $locked */
            $locked = Receipt::query()->ownedBy($user)->whereKey($receipt->getKey())->lockForUpdate()->first();
            if (! $locked instanceof Receipt || $locked->status !== 'needs_review') {
                throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'Only an OCR result awaiting review can create a transaction.');
            }
            if ($locked->review_transaction_id !== null) {
                throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'This receipt already has a review transaction.');
            }
            $transaction = $this->transactions->create($user, [...$attributes, 'state' => 'pending_review', 'source' => 'receipt_ocr']);
            $locked->forceFill(['review_transaction_id' => $transaction->getKey(), 'version' => $locked->version + 1])->save();
            AuditEvent::query()->create(['actor_user_id' => $user->getKey(), 'user_id' => $user->getKey(), 'event_name' => 'receipt.review_transaction_created', 'aggregate_type' => 'receipt', 'aggregate_id' => $locked->getKey(), 'summary' => ['receipt_id' => $locked->getKey(), 'transaction_id' => $transaction->getKey()]]);

            return $transaction;
        }, attempts: 3);
    }
}
