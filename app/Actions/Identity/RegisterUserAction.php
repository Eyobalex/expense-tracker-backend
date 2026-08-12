<?php

namespace App\Actions\Identity;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterUserAction
{
    /**
     * @param  array{name: string, email: string, password: string}  $attributes
     */
    public function execute(array $attributes): User
    {
        return DB::transaction(function () use ($attributes): User {
            $user = User::query()->create([
                'uuid' => (string) Str::uuid(),
                'name' => $attributes['name'],
                'email' => Str::lower($attributes['email']),
                'password' => $attributes['password'],
            ]);
            $user->refresh();

            return $user;
        });
    }
}
