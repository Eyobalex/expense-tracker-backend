<?php

namespace App\Application\Transactions;

use App\Application\Currency\CurrencyRegistry;
use App\Domain\Accounting\Enums\JournalType;
use App\Domain\Accounting\JournalBalanceValidator;
use App\Domain\Accounting\JournalEntryDefinition;
use App\Domain\Accounting\JournalLineDefinition;
use App\Domain\Accounting\ValueObjects\Money;
use App\Domain\Currency\Enums\RoundingMode;
use App\Domain\Currency\ValueObjects\CurrencyCode;
use App\Domain\Currency\ValueObjects\ExchangeRate;
use App\Domain\Shared\Exceptions\DomainErrorCode;
use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Transactions\Enums\TransactionType;
use App\Models\Category;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\User;

final readonly class CanonicalJournalBuilder
{
    public function __construct(
        private CurrencyRegistry $currencies,
        private LedgerAccountResolver $ledgerAccounts,
        private JournalBalanceValidator $balanceValidator,
    ) {}

    public function build(User $user, FinancialTransaction $transaction): JournalEntryDefinition
    {
        $functionalCurrency = CurrencyCode::fromString((string) $user->base_currency_code);
        $account = $transaction->financialAccount;
        if (! $account instanceof FinancialAccount) {
            throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'A financial account is required to post a transaction.');
        }

        $amount = $this->baseAmount($transaction, $functionalCurrency);
        $currency = CurrencyCode::fromString($transaction->original_currency_code);
        $lines = match (TransactionType::from($transaction->type)) {
            TransactionType::Expense, TransactionType::Fee => $this->expenseLines($user, $transaction, $account, $currency, $functionalCurrency, $amount),
            TransactionType::CreditCardPurchase => $this->creditCardPurchaseLines($user, $transaction, $account, $currency, $functionalCurrency, $amount),
            TransactionType::Income => $this->incomeLines($user, $transaction, $account, $currency, $functionalCurrency, $amount),
            TransactionType::Transfer => $this->transferLines($user, $transaction, $account, $currency, $functionalCurrency, $amount),
            TransactionType::CreditCardRepayment => $this->creditCardRepaymentLines($user, $transaction, $account, $currency, $functionalCurrency, $amount),
            TransactionType::Refund => $this->refundLines($user, $transaction, $account, $currency, $functionalCurrency, $amount),
            TransactionType::OpeningBalance => $this->openingBalanceLines($user, $transaction, $account, $currency, $functionalCurrency, $amount),
            TransactionType::Adjustment => $this->adjustmentLines($user, $transaction, $account, $currency, $functionalCurrency, $amount),
        };

        /** @var list<JournalLineDefinition> $lines */
        $entry = new JournalEntryDefinition($this->journalTypeFor($transaction), $functionalCurrency, $lines);
        $this->balanceValidator->assertBalanced($entry);

        return $entry;
    }

    /** @return list<JournalLineDefinition> */
    private function expenseLines(User $user, FinancialTransaction $transaction, FinancialAccount $account, CurrencyCode $currency, CurrencyCode $functionalCurrency, int $amount): array
    {
        $splits = $transaction->splits;
        if ($splits->isEmpty()) {
            $category = $this->requiredCategory($transaction, 'An expense requires a category or reconciled splits.');

            return [
                JournalLineDefinition::debit($this->ledgerAccounts->category($user, $category), $currency, $transaction->original_amount_minor_units, $functionalCurrency, $amount, null, $transaction->description),
                $this->accountLine($user, $account, false, $currency, $transaction->original_amount_minor_units, $functionalCurrency, $amount, $transaction),
            ];
        }

        $lines = [];
        foreach ($splits as $split) {
            $category = $split->category;
            if (! $category instanceof Category) {
                throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'Every expense split requires a category.');
            }
            $splitBase = $this->convertedBaseAmount($split->amount_minor_units, CurrencyCode::fromString($split->currency_code), $functionalCurrency, $transaction);
            $lines[] = JournalLineDefinition::debit($this->ledgerAccounts->category($user, $category), CurrencyCode::fromString($split->currency_code), $split->amount_minor_units, $functionalCurrency, $splitBase, null, $split->description);
        }
        $lines[] = $this->accountLine($user, $account, false, $currency, $transaction->original_amount_minor_units, $functionalCurrency, $amount, $transaction);
        $difference = array_sum(array_map(fn (JournalLineDefinition $line): int => $line->baseDebitMinorUnits, $lines)) - $amount;
        if ($difference !== 0) {
            $code = 'fx.rounding';
            $lines[] = $difference > 0
                ? JournalLineDefinition::credit($this->ledgerAccounts->system($user, $code), $functionalCurrency, $difference, $functionalCurrency, $difference, null, 'Split conversion rounding')
                : JournalLineDefinition::debit($this->ledgerAccounts->system($user, $code), $functionalCurrency, abs($difference), $functionalCurrency, abs($difference), null, 'Split conversion rounding');
        }

        return $lines;
    }

    /** @return list<JournalLineDefinition> */
    private function creditCardPurchaseLines(User $user, FinancialTransaction $transaction, FinancialAccount $account, CurrencyCode $currency, CurrencyCode $functionalCurrency, int $amount): array
    {
        if ($account->type !== 'credit_card') {
            throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'A credit-card purchase requires a credit-card account.');
        }

        return $this->expenseLines($user, $transaction, $account, $currency, $functionalCurrency, $amount);
    }

    /** @return list<JournalLineDefinition> */
    private function incomeLines(User $user, FinancialTransaction $transaction, FinancialAccount $account, CurrencyCode $currency, CurrencyCode $functionalCurrency, int $amount): array
    {
        $category = $this->requiredCategory($transaction, 'Income requires an income category.');
        if ($category->kind !== 'income') {
            throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'Income requires an income category.');
        }

        return [
            $this->accountLine($user, $account, true, $currency, $transaction->original_amount_minor_units, $functionalCurrency, $amount, $transaction),
            JournalLineDefinition::credit($this->ledgerAccounts->category($user, $category), $currency, $transaction->original_amount_minor_units, $functionalCurrency, $amount, null, $transaction->description),
        ];
    }

    /** @return list<JournalLineDefinition> */
    private function transferLines(User $user, FinancialTransaction $transaction, FinancialAccount $source, CurrencyCode $currency, CurrencyCode $functionalCurrency, int $sourceBase): array
    {
        $destination = $transaction->counterpartyAccount;
        if (! $destination instanceof FinancialAccount || $destination->getKey() === $source->getKey()) {
            throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'A transfer requires a different destination account.');
        }
        if ($destination->archived_at !== null) {
            throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'A transfer destination cannot be archived.');
        }
        $destinationAmount = $transaction->counterparty_amount_minor_units ?? $transaction->original_amount_minor_units;
        $destinationCurrency = CurrencyCode::fromString($transaction->counterparty_currency_code ?? $destination->currency_code);
        if ($destinationCurrency->toString() !== $destination->currency_code) {
            throw DomainException::for(DomainErrorCode::CurrencyMismatch, 'The destination transfer currency must match the destination account.');
        }
        $destinationBase = $this->convertedBaseAmount($destinationAmount, $destinationCurrency, $functionalCurrency, $transaction);
        $lines = [
            $this->accountLine($user, $destination, true, $destinationCurrency, $destinationAmount, $functionalCurrency, $destinationBase, $transaction),
            $this->accountLine($user, $source, false, $currency, $transaction->original_amount_minor_units, $functionalCurrency, $sourceBase, $transaction),
        ];
        $difference = $destinationBase - $sourceBase;
        if ($difference !== 0) {
            $code = abs($difference) <= 1 ? 'fx.rounding' : 'fx.gain_loss';
            $lines[] = $difference > 0
                ? JournalLineDefinition::credit($this->ledgerAccounts->system($user, $code), $functionalCurrency, $difference, $functionalCurrency, $difference, null, 'FX difference')
                : JournalLineDefinition::debit($this->ledgerAccounts->system($user, $code), $functionalCurrency, abs($difference), $functionalCurrency, abs($difference), null, 'FX difference');
        }

        return $lines;
    }

    /** @return list<JournalLineDefinition> */
    private function creditCardRepaymentLines(User $user, FinancialTransaction $transaction, FinancialAccount $card, CurrencyCode $currency, CurrencyCode $functionalCurrency, int $amount): array
    {
        if ($card->type !== 'credit_card') {
            throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'A credit-card repayment requires the credit-card account as the primary account.');
        }
        $bank = $transaction->counterpartyAccount;
        if (! $bank instanceof FinancialAccount || $bank->accounting_type !== 'asset') {
            throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'A credit-card repayment requires an asset account as its payment account.');
        }
        if ($bank->currency_code !== $currency->toString()) {
            throw DomainException::for(DomainErrorCode::CurrencyMismatch, 'A credit-card repayment currently requires matching account currencies.');
        }

        return [
            $this->accountLine($user, $card, true, $currency, $transaction->original_amount_minor_units, $functionalCurrency, $amount, $transaction),
            $this->accountLine($user, $bank, false, $currency, $transaction->original_amount_minor_units, $functionalCurrency, $amount, $transaction),
        ];
    }

    /** @return list<JournalLineDefinition> */
    private function refundLines(User $user, FinancialTransaction $transaction, FinancialAccount $account, CurrencyCode $currency, CurrencyCode $functionalCurrency, int $amount): array
    {
        $original = $transaction->relatedTransaction;
        if (! $original instanceof FinancialTransaction || ! in_array($original->type, ['expense', 'fee', 'credit_card_purchase'], true)) {
            throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'A refund must link to an original posted expense transaction.');
        }
        $category = $original->category ?? $transaction->category;
        if (! $category instanceof Category) {
            throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'A refund must link to an original posted expense transaction.');
        }

        return [
            $this->accountLine($user, $account, true, $currency, $transaction->original_amount_minor_units, $functionalCurrency, $amount, $transaction),
            JournalLineDefinition::credit($this->ledgerAccounts->category($user, $category), $currency, $transaction->original_amount_minor_units, $functionalCurrency, $amount, null, 'Refund of '.$original->getKey()),
        ];
    }

    /** @return list<JournalLineDefinition> */
    private function openingBalanceLines(User $user, FinancialTransaction $transaction, FinancialAccount $account, CurrencyCode $currency, CurrencyCode $functionalCurrency, int $amount): array
    {
        $isAsset = $account->accounting_type === 'asset';

        return $isAsset ? [
            $this->accountLine($user, $account, true, $currency, $transaction->original_amount_minor_units, $functionalCurrency, $amount, $transaction),
            JournalLineDefinition::credit($this->ledgerAccounts->system($user, 'equity.opening_balance'), $functionalCurrency, $amount, $functionalCurrency, $amount, null, 'Opening balance'),
        ] : [
            JournalLineDefinition::debit($this->ledgerAccounts->system($user, 'equity.opening_balance'), $functionalCurrency, $amount, $functionalCurrency, $amount, null, 'Opening balance'),
            $this->accountLine($user, $account, false, $currency, $transaction->original_amount_minor_units, $functionalCurrency, $amount, $transaction),
        ];
    }

    /** @return list<JournalLineDefinition> */
    private function adjustmentLines(User $user, FinancialTransaction $transaction, FinancialAccount $account, CurrencyCode $currency, CurrencyCode $functionalCurrency, int $amount): array
    {
        if ($transaction->adjustment_subtype !== 'balance_correction' || ! in_array($transaction->adjustment_direction, ['debit', 'credit'], true) || $transaction->reason === null) {
            throw DomainException::for(DomainErrorCode::UnauthorizedAction, 'Only a reasoned balance correction can be manually posted as an adjustment.');
        }
        $counterpart = $this->ledgerAccounts->system($user, 'equity.balance_correction');

        return $transaction->adjustment_direction === 'debit' ? [
            $this->accountLine($user, $account, true, $currency, $transaction->original_amount_minor_units, $functionalCurrency, $amount, $transaction),
            JournalLineDefinition::credit($counterpart, $functionalCurrency, $amount, $functionalCurrency, $amount, null, $transaction->reason),
        ] : [
            JournalLineDefinition::debit($counterpart, $functionalCurrency, $amount, $functionalCurrency, $amount, null, $transaction->reason),
            $this->accountLine($user, $account, false, $currency, $transaction->original_amount_minor_units, $functionalCurrency, $amount, $transaction),
        ];
    }

    private function accountLine(User $user, FinancialAccount $account, bool $debit, CurrencyCode $currency, int $nativeAmount, CurrencyCode $functionalCurrency, int $baseAmount, FinancialTransaction $transaction): JournalLineDefinition
    {
        return $debit
            ? JournalLineDefinition::debit($this->ledgerAccounts->financialAccount($user, $account), $currency, $nativeAmount, $functionalCurrency, $baseAmount, $account->getKey(), $transaction->description)
            : JournalLineDefinition::credit($this->ledgerAccounts->financialAccount($user, $account), $currency, $nativeAmount, $functionalCurrency, $baseAmount, $account->getKey(), $transaction->description);
    }

    private function baseAmount(FinancialTransaction $transaction, CurrencyCode $functionalCurrency): int
    {
        return $this->convertedBaseAmount($transaction->original_amount_minor_units, CurrencyCode::fromString($transaction->original_currency_code), $functionalCurrency, $transaction);
    }

    private function convertedBaseAmount(int $minorUnits, CurrencyCode $currency, CurrencyCode $functionalCurrency, FinancialTransaction $transaction): int
    {
        if ($currency->equals($functionalCurrency)) {
            return $minorUnits;
        }
        if ($transaction->used_rate === null || $transaction->rounding_mode === null) {
            throw DomainException::for(DomainErrorCode::InvalidRateLock, 'A locked exchange rate and rounding mode are required for a non-base-currency transaction.');
        }

        return ExchangeRate::fromDecimal((string) $transaction->used_rate)->convert(
            new Money($minorUnits, $currency),
            $this->currencies->activeMetadata($currency),
            $this->currencies->activeMetadata($functionalCurrency),
            RoundingMode::from($transaction->rounding_mode),
        )->minorUnits;
    }

    private function requiredCategory(FinancialTransaction $transaction, string $message): Category
    {
        if (! $transaction->category instanceof Category) {
            throw DomainException::for(DomainErrorCode::InvalidStateTransition, $message);
        }

        return $transaction->category;
    }

    private function journalTypeFor(FinancialTransaction $transaction): JournalType
    {
        return match ($transaction->type) {
            'opening_balance' => JournalType::OpeningBalance,
            'fee' => JournalType::Fee,
            default => JournalType::Normal,
        };
    }
}
