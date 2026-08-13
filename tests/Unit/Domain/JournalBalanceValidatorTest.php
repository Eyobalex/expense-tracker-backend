<?php

use App\Domain\Accounting\Enums\JournalType;
use App\Domain\Accounting\JournalBalanceValidator;
use App\Domain\Accounting\JournalEntryDefinition;
use App\Domain\Accounting\JournalLineDefinition;
use App\Domain\Currency\ValueObjects\CurrencyCode;
use App\Domain\Shared\Exceptions\DomainException;

it('preserves functional-currency balance for deterministic cross-currency rounding cases', function (): void {
    $etb = CurrencyCode::fromString('ETB');
    $usd = CurrencyCode::fromString('USD');

    foreach ([[100, 5500], [101, 5555], [9999, 549945]] as [$usdMinor, $etbMinor]) {
        $entry = new JournalEntryDefinition(JournalType::Normal, $etb, [
            JournalLineDefinition::debit('asset.etb', $etb, $etbMinor, $etb, $etbMinor),
            JournalLineDefinition::credit('asset.usd', $usd, $usdMinor, $etb, $etbMinor),
        ]);

        app(JournalBalanceValidator::class)->assertBalanced($entry);
    }

    expect(true)->toBeTrue();
});

it('accepts a balanced functional-currency journal', function (): void {
    $etb = CurrencyCode::fromString('ETB');
    $entry = new JournalEntryDefinition(JournalType::Normal, $etb, [
        JournalLineDefinition::debit('asset.cash', $etb, 1000, $etb, 1000),
        JournalLineDefinition::credit('expense.food', $etb, 1000, $etb, 1000),
    ]);

    app(JournalBalanceValidator::class)->assertBalanced($entry);

    expect(true)->toBeTrue();
});

it('rejects an unbalanced functional-currency journal even when native amounts look valid', function (): void {
    $etb = CurrencyCode::fromString('ETB');
    $usd = CurrencyCode::fromString('USD');
    $entry = new JournalEntryDefinition(JournalType::Normal, $etb, [
        JournalLineDefinition::debit('asset.usd', $usd, 100, $etb, 5500),
        JournalLineDefinition::credit('asset.etb', $etb, 5501, $etb, 5501),
    ]);

    expect(fn (): null => app(JournalBalanceValidator::class)->assertBalanced($entry))->toThrow(DomainException::class, 'Functional journal debits must equal functional journal credits.');
});

it('rejects a journal line with both debit and credit values', function (): void {
    $etb = CurrencyCode::fromString('ETB');

    expect(fn (): JournalLineDefinition => new JournalLineDefinition('invalid', $etb, 1, 1, $etb, 1, 1))->toThrow(DomainException::class);
});
