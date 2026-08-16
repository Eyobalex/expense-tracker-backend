<?php

namespace App\Console\Commands;

use App\Jobs\EvaluateUserNotifications;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('notifications:evaluate')]
#[Description('Queue notification evaluation for users with authoritative financial history.')]
class EvaluateNotifications extends Command
{
    public function handle(): int
    {
        User::query()->select('id')->orderBy('id')->chunkById(200, function ($users): void {
            foreach ($users as $user) {
                EvaluateUserNotifications::dispatch($user->id);
            }
        });

        return self::SUCCESS;
    }
}
