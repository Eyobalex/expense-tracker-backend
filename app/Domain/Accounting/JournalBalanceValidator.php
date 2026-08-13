<?php

namespace App\Domain\Accounting;

use App\Domain\Shared\Exceptions\DomainErrorCode;
use App\Domain\Shared\Exceptions\DomainException;

final class JournalBalanceValidator
{
    public function assertBalanced(JournalEntryDefinition $entry): void
    {
        $debits = 0;
        $credits = 0;

        foreach ($entry->lines as $line) {
            if (! $line->baseCurrency->equals($entry->functionalCurrency)) {
                throw DomainException::for(DomainErrorCode::CurrencyMismatch, 'Every journal line must use the entry functional currency.');
            }

            $debits += $line->baseDebitMinorUnits;
            $credits += $line->baseCreditMinorUnits;
        }

        if (count($entry->lines) < 2 || $debits !== $credits) {
            throw DomainException::for(DomainErrorCode::InvalidMoney, 'Functional journal debits must equal functional journal credits.');
        }
    }
}
