<?php

namespace App\Application\Transactions;

use App\Application\Budgeting\RecalculateBudgetChain;
use App\Application\Currency\TransactionRateLockingService;
use App\Domain\Accounting\JournalEntryDefinition;
use App\Domain\Shared\Exceptions\DomainErrorCode;
use App\Domain\Shared\Exceptions\DomainException;
use App\Models\AuditEvent;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\JournalEntry;
use App\Models\TransactionSplit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class TransactionService
{
    public function __construct(
        private CanonicalJournalBuilder $journals,
        private RecalculateBudgetChain $budgets,
        private DuplicateDetectionService $duplicates,
        private TransactionRateLockingService $rates,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $user, array $attributes): FinancialTransaction
    {
        $transaction = DB::transaction(function () use ($user, $attributes): FinancialTransaction {
            $this->assertAccount($user, (string) $attributes['financial_account_id']);
            $this->assertRelatedOwnership($user, $attributes);
            $transaction = $user->financialTransactions()->create($this->transactionAttributes($attributes));
            $this->replaceSplits($transaction, $attributes['splits'] ?? []);

            return $transaction->load(['financialAccount', 'counterpartyAccount', 'category', 'relatedTransaction', 'splits.category']);
        }, attempts: 3);

        $this->duplicates->discover($user, $transaction);

        return $transaction;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $user, FinancialTransaction $transaction, int $expectedVersion, array $attributes): FinancialTransaction
    {
        if ($transaction->user_id !== $user->id) {
            throw DomainException::for(DomainErrorCode::ResourceNotFound, 'The transaction was not found.');
        }
        if (! in_array($transaction->state, ['draft', 'pending_review'], true)) {
            throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'Only draft or pending-review transactions can be edited.');
        }

        $updatedTransaction = DB::transaction(function () use ($user, $transaction, $expectedVersion, $attributes): FinancialTransaction {
            $this->assertRelatedOwnership($user, $attributes);
            $values = $this->transactionAttributes($attributes, false);
            $values['version'] = $expectedVersion + 1;
            $updated = FinancialTransaction::query()->ownedBy($user)->whereKey($transaction->getKey())->where('version', $expectedVersion)->update($values);
            if ($updated !== 1) {
                throw DomainException::for(DomainErrorCode::ConcurrencyConflict, 'The transaction version is stale.');
            }
            /** @var FinancialTransaction $fresh */
            $fresh = $transaction->fresh();
            if (array_key_exists('splits', $attributes)) {
                $this->replaceSplits($fresh, $attributes['splits']);
            }

            return $fresh->load(['financialAccount', 'counterpartyAccount', 'category', 'relatedTransaction', 'splits.category']);
        }, attempts: 3);

        $this->duplicates->discover($user, $updatedTransaction);

        return $updatedTransaction;
    }

    public function delete(User $user, FinancialTransaction $transaction, int $expectedVersion): void
    {
        DB::transaction(function () use ($user, $transaction, $expectedVersion): void {
            $deleted = FinancialTransaction::query()->ownedBy($user)->whereKey($transaction->getKey())->whereIn('state', ['draft', 'pending_review'])->where('version', $expectedVersion)->delete();
            if ($deleted !== 1) {
                throw DomainException::for(DomainErrorCode::ConcurrencyConflict, 'The transaction is stale or cannot be deleted after posting.');
            }
        }, attempts: 3);
    }

    public function post(User $user, FinancialTransaction $transaction, int $expectedVersion): FinancialTransaction
    {
        return DB::transaction(function () use ($user, $transaction, $expectedVersion): FinancialTransaction {
            /** @var FinancialTransaction|null $locked */
            $locked = FinancialTransaction::query()->ownedBy($user)->whereKey($transaction->getKey())->lockForUpdate()->first();
            if (! $locked instanceof FinancialTransaction) {
                throw DomainException::for(DomainErrorCode::ResourceNotFound, 'The transaction was not found.');
            }
            if ($locked->version !== $expectedVersion) {
                throw DomainException::for(DomainErrorCode::ConcurrencyConflict, 'The transaction version is stale.');
            }
            if (! in_array($locked->state, ['draft', 'pending_review'], true)) {
                throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'Only a draft or pending-review transaction can be posted.');
            }
            $locked->load(['financialAccount', 'counterpartyAccount', 'category', 'relatedTransaction.category', 'splits.category']);
            $this->duplicates->assertNoUnresolvedCandidates($user, $locked);
            $this->rates->lockForPosting($user, $locked);
            if ($locked->rate_source === 'manual_override') {
                $this->audit($user, 'transaction.fx_rate_overridden', $locked, [
                    'reference_rate' => $locked->reference_rate,
                    'used_rate' => $locked->used_rate,
                    'rate_override_reason' => $locked->rate_override_reason,
                ]);
            }
            $this->assertPostable($user, $locked);
            $definition = $this->journals->build($user, $locked);
            $entry = $this->persistJournal($user, $locked, $definition);
            $now = now();
            $locked->forceFill([
                'state' => 'posted', 'base_amount_minor_units' => $this->baseAmountFrom($definition, $locked),
                'base_currency_code' => $user->base_currency_code, 'journal_entry_id' => $entry->getKey(),
                'posted_at' => $now, 'version' => $locked->version + 1,
            ])->save();
            $this->createHistoryLocks($user, $locked, $now);
            $this->budgets->forTransaction($locked);
            $this->audit($user, 'transaction.posted', $locked, ['journal_entry_id' => $entry->getKey()]);

            return $locked->fresh()->load(['financialAccount', 'counterpartyAccount', 'category', 'relatedTransaction', 'splits.category', 'journalEntry.lines']);
        }, attempts: 3);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function correct(User $user, FinancialTransaction $transaction, int $expectedVersion, string $reason, array $attributes): FinancialTransaction
    {
        return DB::transaction(function () use ($user, $transaction, $expectedVersion, $reason, $attributes): FinancialTransaction {
            $this->reverse($user, $transaction, $expectedVersion, $reason);
            $replacement = $this->create($user, [...$attributes, 'correction_of_id' => $transaction->getKey()]);

            return $this->post($user, $replacement, $replacement->version);
        }, attempts: 3);
    }

    public function reverse(User $user, FinancialTransaction $transaction, int $expectedVersion, string $reason): FinancialTransaction
    {
        return DB::transaction(function () use ($user, $transaction, $expectedVersion, $reason): FinancialTransaction {
            /** @var FinancialTransaction|null $original */
            $original = FinancialTransaction::query()->ownedBy($user)->whereKey($transaction->getKey())->lockForUpdate()->first();
            if (! $original instanceof FinancialTransaction) {
                throw DomainException::for(DomainErrorCode::ResourceNotFound, 'The transaction was not found.');
            }
            if ($original->version !== $expectedVersion) {
                throw DomainException::for(DomainErrorCode::ConcurrencyConflict, 'The transaction version is stale.');
            }
            if ($original->state !== 'posted' || FinancialTransaction::query()->where('reversal_of_id', $original->getKey())->exists()) {
                throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'A posted transaction may be reversed exactly once.');
            }
            $reversal = $user->financialTransactions()->create([
                ...$original->only(['financial_account_id', 'counterparty_account_id', 'category_id', 'merchant_id', 'raw_merchant_text', 'related_transaction_id', 'correction_of_id', 'type', 'source', 'adjustment_subtype', 'adjustment_direction', 'occurred_at', 'occurred_timezone', 'original_amount_minor_units', 'original_currency_code', 'counterparty_amount_minor_units', 'counterparty_currency_code', 'reference_rate', 'used_rate', 'rate_date', 'rate_source', 'rate_override_reason', 'rounding_mode', 'description', 'reference_number']),
                'state' => 'draft', 'reason' => $reason, 'reversal_of_id' => $original->getKey(), 'base_amount_minor_units' => $original->base_amount_minor_units, 'base_currency_code' => $original->base_currency_code,
            ]);
            $entry = $this->persistOppositeJournal($user, $original, $reversal);
            $reversal->forceFill(['state' => 'reversed', 'journal_entry_id' => $entry->getKey(), 'posted_at' => now()])->save();
            $original->forceFill(['state' => 'reversed', 'reversed_at' => now(), 'version' => $original->version + 1])->save();
            $this->budgets->forTransactionEffectRemoved($original);
            $this->audit($user, 'transaction.reversed', $original, ['reversal_transaction_id' => $reversal->getKey(), 'reason' => $reason]);

            return $reversal->fresh()->load(['journalEntry.lines']);
        }, attempts: 3);
    }

    private function assertPostable(User $user, FinancialTransaction $transaction): void
    {
        $account = $transaction->financialAccount;
        if (! $account instanceof FinancialAccount || $account->archived_at !== null || $account->currency_code !== $transaction->original_currency_code) {
            throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'The primary account must be active and match the transaction currency.');
        }
        $splits = $transaction->splits;
        if ($splits->isNotEmpty()) {
            $total = $splits->sum('amount_minor_units');
            if ($total !== $transaction->original_amount_minor_units || $splits->contains(fn (TransactionSplit $split): bool => $split->currency_code !== $transaction->original_currency_code)) {
                throw DomainException::for(DomainErrorCode::InvalidMoney, 'Transaction splits must reconcile exactly to the transaction amount and currency.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function transactionAttributes(array $attributes, bool $creating = true): array
    {
        $keys = ['financial_account_id', 'counterparty_account_id', 'category_id', 'merchant_id', 'raw_merchant_text', 'related_transaction_id', 'correction_of_id', 'type', 'state', 'source', 'adjustment_subtype', 'adjustment_direction', 'reason', 'occurred_at', 'occurred_timezone', 'original_amount_minor_units', 'original_currency_code', 'counterparty_amount_minor_units', 'counterparty_currency_code', 'reference_rate', 'used_rate', 'rate_date', 'rate_source', 'rate_override_reason', 'rounding_mode', 'description', 'reference_number'];
        $values = array_intersect_key($attributes, array_flip($keys));
        if ($creating) {
            $values += ['state' => 'draft', 'source' => 'manual'];
        }

        return $values;
    }

    /** @param list<array<string, mixed>> $splits */
    private function replaceSplits(FinancialTransaction $transaction, array $splits): void
    {
        $transaction->splits()->delete();
        foreach ($splits as $sequence => $split) {
            $transaction->splits()->create([...$split, 'sequence' => $sequence]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function assertRelatedOwnership(User $user, array $attributes): void
    {
        foreach (['financial_account_id', 'counterparty_account_id'] as $key) {
            if (isset($attributes[$key])) {
                $this->assertAccount($user, (string) $attributes[$key]);
            }
        }
        if (isset($attributes['merchant_id']) && ! $user->merchants()->whereKey($attributes['merchant_id'])->whereNull('merged_into_id')->where('is_active', true)->exists()) {
            throw DomainException::for(DomainErrorCode::ResourceNotFound, 'The transaction merchant was not found.');
        }
        if (isset($attributes['category_id']) && ! $user->categories()->whereKey($attributes['category_id'])->whereNull('archived_at')->exists()) {
            throw DomainException::for(DomainErrorCode::ResourceNotFound, 'The transaction category was not found.');
        }
        if (isset($attributes['related_transaction_id']) && ! $user->financialTransactions()->whereKey($attributes['related_transaction_id'])->where('state', 'posted')->exists()) {
            throw DomainException::for(DomainErrorCode::ResourceNotFound, 'The related posted transaction was not found.');
        }
        foreach ($attributes['splits'] ?? [] as $split) {
            if (! isset($split['category_id']) || ! $user->categories()->whereKey($split['category_id'])->whereNull('archived_at')->exists()) {
                throw DomainException::for(DomainErrorCode::ResourceNotFound, 'A transaction split category was not found.');
            }
            if (isset($split['canonical_item_id']) && ! $user->items()->whereKey($split['canonical_item_id'])->whereNull('merged_into_id')->where('is_active', true)->exists()) {
                throw DomainException::for(DomainErrorCode::ResourceNotFound, 'A transaction split item was not found.');
            }
        }
    }

    private function assertAccount(User $user, string $accountId): void
    {
        if (! $user->financialAccounts()->whereKey($accountId)->exists()) {
            throw DomainException::for(DomainErrorCode::ResourceNotFound, 'The financial account was not found.');
        }
    }

    private function persistJournal(User $user, FinancialTransaction $transaction, JournalEntryDefinition $definition): JournalEntry
    {
        $entry = JournalEntry::query()->create(['user_id' => $user->getKey(), 'financial_transaction_id' => $transaction->getKey(), 'type' => $definition->type->value, 'functional_currency_code' => $definition->functionalCurrency->toString(), 'posted_at' => now()]);
        foreach ($definition->lines as $sequence => $line) {
            $entry->lines()->create(['user_id' => $user->getKey(), 'financial_account_id' => $line->financialAccountId, 'ledger_code' => $line->ledgerCode, 'debit_minor_units' => $line->debitMinorUnits, 'credit_minor_units' => $line->creditMinorUnits, 'currency_code' => $line->currency->toString(), 'base_debit_minor_units' => $line->baseDebitMinorUnits, 'base_credit_minor_units' => $line->baseCreditMinorUnits, 'base_currency_code' => $line->baseCurrency->toString(), 'used_rate' => $transaction->used_rate, 'rate_date' => $transaction->rate_date, 'rate_source' => $transaction->rate_source, 'description' => $line->description, 'sequence' => $sequence]);
        }

        return $entry;
    }

    private function persistOppositeJournal(User $user, FinancialTransaction $original, FinancialTransaction $reversal): JournalEntry
    {
        $entry = JournalEntry::query()->create(['user_id' => $user->getKey(), 'financial_transaction_id' => $reversal->getKey(), 'type' => 'reversal', 'functional_currency_code' => $original->base_currency_code, 'posted_at' => now(), 'reversed_entry_id' => $original->journal_entry_id]);
        foreach (JournalEntry::query()->findOrFail($original->journal_entry_id)->lines as $sequence => $line) {
            $entry->lines()->create(['user_id' => $user->getKey(), 'financial_account_id' => $line->financial_account_id, 'ledger_code' => $line->ledger_code, 'debit_minor_units' => $line->credit_minor_units, 'credit_minor_units' => $line->debit_minor_units, 'currency_code' => $line->currency_code, 'base_debit_minor_units' => $line->base_credit_minor_units, 'base_credit_minor_units' => $line->base_debit_minor_units, 'base_currency_code' => $line->base_currency_code, 'used_rate' => $line->used_rate, 'rate_date' => $line->rate_date, 'rate_source' => $line->rate_source, 'description' => 'Reversal: '.$reversal->reason, 'sequence' => $sequence]);
        }

        return $entry;
    }

    private function baseAmountFrom(JournalEntryDefinition $definition, FinancialTransaction $transaction): int
    {
        foreach ($definition->lines as $line) {
            if ($line->financialAccountId === $transaction->financial_account_id) {
                return max($line->baseDebitMinorUnits, $line->baseCreditMinorUnits);
            }
        }

        throw DomainException::for(DomainErrorCode::InvalidMoney, 'The journal does not include the primary financial account.');
    }

    /** @param array<string, mixed> $summary */
    private function audit(User $user, string $eventName, FinancialTransaction $transaction, array $summary): void
    {
        AuditEvent::query()->create(['actor_user_id' => $user->getKey(), 'user_id' => $user->getKey(), 'event_name' => $eventName, 'aggregate_type' => 'financial_transaction', 'aggregate_id' => $transaction->getKey(), 'summary' => $summary]);
    }

    private function createHistoryLocks(User $user, FinancialTransaction $transaction, mixed $postedAt): void
    {
        DB::table('financial_history_locks')->insertOrIgnore(['user_id' => $user->getKey(), 'financial_account_id' => null, 'scope' => 'base_currency', 'first_posted_at' => $postedAt, 'created_at' => $postedAt, 'updated_at' => $postedAt]);
        foreach (array_filter([$transaction->financial_account_id, $transaction->counterparty_account_id]) as $accountId) {
            DB::table('financial_history_locks')->insertOrIgnore(['user_id' => $user->getKey(), 'financial_account_id' => $accountId, 'scope' => 'account_currency', 'first_posted_at' => $postedAt, 'created_at' => $postedAt, 'updated_at' => $postedAt]);
        }
    }
}
