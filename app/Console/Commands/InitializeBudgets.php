<?php

namespace App\Console\Commands;

use App\Application\Budgeting\BudgetService;
use App\Domain\Shared\Time\MonthlyPeriod;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('budget:initialize {--month= : Initialize a specific YYYY-MM month in each user budget timezone.}')]
#[Description('Idempotently initialize monthly budget periods for active budget categories.')]
class InitializeBudgets extends Command
{
    public function handle(BudgetService $budgets): int
    {
        $month = $this->option('month');
        if ($month !== null && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) !== 1) {
            $this->error('The --month option must use YYYY-MM.');

            return self::FAILURE;
        }

        User::query()->orderBy('id')->each(function (User $user) use ($budgets, $month): void {
            $period = $month !== null
                ? MonthlyPeriod::forMonth((int) substr($month, 0, 4), (int) substr($month, 5, 2), $user->budget_timezone)
                : MonthlyPeriod::containing(now(), $user->budget_timezone);
            $budgets->periodsFor($user, $period);
        });

        return self::SUCCESS;
    }
}
