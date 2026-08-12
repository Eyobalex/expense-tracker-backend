<?php

namespace App\Application\Accounts;

use App\Models\FinancialAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class FinancialHistoryInspector
{
    public function userHasPostedTransactions(User $user): bool
    {
        return Schema::hasTable('financial_history_locks')
            && DB::table('financial_history_locks')->where('user_id', $user->getKey())->where('scope', 'base_currency')->exists();
    }

    public function accountHasPostedJournalHistory(FinancialAccount $account): bool
    {
        return Schema::hasTable('financial_history_locks')
            && DB::table('financial_history_locks')->where('financial_account_id', $account->getKey())->exists();
    }
}
