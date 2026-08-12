<?php

namespace App\Application\Accounts;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class StarterDataService
{
    public function seedFor(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $cash = $user->financialAccounts()->firstOrCreate(
                ['name' => 'Cash'],
                ['type' => 'cash', 'accounting_type' => 'asset', 'currency_code' => $user->base_currency_code, 'opening_balance_configured' => false],
            );

            foreach (['Food', 'Transport', 'Housing'] as $name) {
                $user->categories()->firstOrCreate(
                    ['parent_id' => null, 'name' => $name],
                    ['kind' => 'expense', 'is_system' => true],
                );
            }
            foreach (['Salary', 'Other income'] as $name) {
                $user->categories()->firstOrCreate(
                    ['parent_id' => null, 'name' => $name],
                    ['kind' => 'income', 'is_system' => true],
                );
            }

            $user->ledgerAccountMappings()->firstOrCreate(
                ['entity_type' => 'financial_account', 'entity_id' => $cash->getKey(), 'mapping_key' => 'balance'],
                ['ledger_code' => 'asset.cash.'.$cash->getKey()],
            );
            foreach (['equity.opening_balance', 'expense.fees', 'income.refunds', 'fx.rounding', 'fx.gain_loss'] as $code) {
                $user->ledgerAccountMappings()->firstOrCreate(
                    ['entity_type' => 'system', 'entity_id' => null, 'mapping_key' => $code],
                    ['ledger_code' => $code],
                );
            }
        });
    }
}
