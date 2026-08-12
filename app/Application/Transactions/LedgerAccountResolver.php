<?php

namespace App\Application\Transactions;

use App\Models\Category;
use App\Models\FinancialAccount;
use App\Models\LedgerAccountMapping;
use App\Models\User;

final class LedgerAccountResolver
{
    public function financialAccount(User $user, FinancialAccount $account): string
    {
        return $this->code($user, 'financial_account', $account->getKey(), 'balance', $account->accounting_type.'.financial.'.$account->getKey());
    }

    public function category(User $user, Category $category): string
    {
        return $this->code($user, 'category', $category->getKey(), 'allocation', $category->kind.'.category.'.$category->getKey());
    }

    public function system(User $user, string $mappingKey): string
    {
        return $this->code($user, 'system', null, $mappingKey, $mappingKey);
    }

    private function code(User $user, string $entityType, ?string $entityId, string $mappingKey, string $default): string
    {
        /** @var LedgerAccountMapping $mapping */
        $mapping = LedgerAccountMapping::query()->firstOrCreate(
            ['user_id' => $user->getKey(), 'entity_type' => $entityType, 'entity_id' => $entityId, 'mapping_key' => $mappingKey],
            ['ledger_code' => $default],
        );

        return $mapping->ledger_code;
    }
}
