<?php

namespace App\Domain\Accounting;

use App\Domain\Currency\ValueObjects\CurrencyCode;
use App\Domain\Shared\Exceptions\DomainErrorCode;
use App\Domain\Shared\Exceptions\DomainException;

final readonly class JournalLineDefinition
{
    public function __construct(
        public string $ledgerCode,
        public CurrencyCode $currency,
        public int $debitMinorUnits,
        public int $creditMinorUnits,
        public CurrencyCode $baseCurrency,
        public int $baseDebitMinorUnits,
        public int $baseCreditMinorUnits,
        public ?string $financialAccountId = null,
        public ?string $description = null,
    ) {
        $this->assertOnePositiveSide($debitMinorUnits, $creditMinorUnits);
        $this->assertOnePositiveSide($baseDebitMinorUnits, $baseCreditMinorUnits);
    }

    public static function debit(string $ledgerCode, CurrencyCode $currency, int $minorUnits, CurrencyCode $baseCurrency, int $baseMinorUnits, ?string $financialAccountId = null, ?string $description = null): self
    {
        return new self($ledgerCode, $currency, $minorUnits, 0, $baseCurrency, $baseMinorUnits, 0, $financialAccountId, $description);
    }

    public static function credit(string $ledgerCode, CurrencyCode $currency, int $minorUnits, CurrencyCode $baseCurrency, int $baseMinorUnits, ?string $financialAccountId = null, ?string $description = null): self
    {
        return new self($ledgerCode, $currency, 0, $minorUnits, $baseCurrency, 0, $baseMinorUnits, $financialAccountId, $description);
    }

    private function assertOnePositiveSide(int $debit, int $credit): void
    {
        if (($debit > 0 && $credit === 0) || ($credit > 0 && $debit === 0)) {
            return;
        }

        throw DomainException::for(DomainErrorCode::InvalidMoney, 'A journal line must have exactly one positive debit or credit side.');
    }
}
