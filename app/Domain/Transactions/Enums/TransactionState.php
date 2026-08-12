<?php

namespace App\Domain\Transactions\Enums;

enum TransactionState: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Posted = 'posted';
    case Reversed = 'reversed';
    case SyncConflict = 'sync_conflict';
}
