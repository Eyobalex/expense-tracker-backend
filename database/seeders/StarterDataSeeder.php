<?php

namespace Database\Seeders;

use App\Application\Accounts\StarterDataService;
use App\Models\User;
use Illuminate\Database\Seeder;

class StarterDataSeeder extends Seeder
{
    public function run(StarterDataService $starterData): void
    {
        User::query()->whereNotNull('base_currency_code')->each(
            fn (User $user): mixed => $starterData->seedFor($user),
        );
    }
}
