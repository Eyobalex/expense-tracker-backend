<?php

namespace App\Application\Accounts;

use App\Application\Currency\CurrencyRegistry;
use App\Domain\Shared\Exceptions\DomainErrorCode;
use App\Domain\Shared\Exceptions\DomainException;
use App\Models\FinancialAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class FinancialAccountService
{
    public function __construct(
        private FinancialHistoryInspector $financialHistory,
        private CurrencyRegistry $currencies,
    ) {}

    /** @param array{name: string, type: string, currency_code: string, opening_balance_configured?: bool} $attributes */
    public function create(User $user, array $attributes): FinancialAccount
    {
        $this->currencies->activeMetadata($attributes['currency_code']);

        return DB::transaction(function () use ($user, $attributes): FinancialAccount {
            $account = $user->financialAccounts()->create([
                'name' => $attributes['name'],
                'type' => $attributes['type'],
                'accounting_type' => $this->accountingTypeFor($attributes['type']),
                'currency_code' => strtoupper($attributes['currency_code']),
                'opening_balance_configured' => $attributes['opening_balance_configured'] ?? false,
            ]);

            return $account->refresh();
        });
    }

    /** @param array{name?: string, type?: string, currency_code?: string, opening_balance_configured?: bool} $attributes */
    public function update(User $user, FinancialAccount $account, int $expectedVersion, array $attributes): FinancialAccount
    {
        if ($account->user_id !== $user->id) {
            throw DomainException::for(DomainErrorCode::ResourceNotFound, 'The financial account was not found.');
        }

        if (array_key_exists('currency_code', $attributes)
            && strtoupper($attributes['currency_code']) !== $account->currency_code
            && $this->financialHistory->accountHasPostedJournalHistory($account)) {
            throw DomainException::for(DomainErrorCode::AccountCurrencyLocked, 'An account currency cannot change after posted journal history exists.');
        }

        if (array_key_exists('currency_code', $attributes)) {
            $this->currencies->activeMetadata($attributes['currency_code']);
        }

        $updates = $attributes;
        if (array_key_exists('type', $updates)) {
            $updates['accounting_type'] = $this->accountingTypeFor($updates['type']);
        }
        if (array_key_exists('currency_code', $updates)) {
            $updates['currency_code'] = strtoupper($updates['currency_code']);
        }
        $updates['version'] = $expectedVersion + 1;

        $updated = FinancialAccount::query()->ownedBy($user)->whereKey($account->getKey())->where('version', $expectedVersion)->update($updates);
        if ($updated !== 1) {
            throw DomainException::for(DomainErrorCode::ConcurrencyConflict, 'The financial account version is stale.');
        }

        /** @var FinancialAccount $fresh */
        $fresh = $account->fresh();

        return $fresh;
    }

    public function archive(User $user, FinancialAccount $account, int $expectedVersion): FinancialAccount
    {
        return $this->update($user, $account, $expectedVersion, ['archived_at' => now()]);
    }

    public function restore(User $user, FinancialAccount $account, int $expectedVersion): FinancialAccount
    {
        return $this->update($user, $account, $expectedVersion, ['archived_at' => null]);
    }

    private function accountingTypeFor(string $type): string
    {
        return in_array($type, ['credit_card', 'loan'], true) ? 'liability' : 'asset';
    }
}
