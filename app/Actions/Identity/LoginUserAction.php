<?php

namespace App\Actions\Identity;

use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\NewAccessToken;

class LoginUserAction
{
    /**
     * @param  array{client_device_id: string, platform: string, app_version?: string|null}  $deviceAttributes
     * @return array{device: Device, token: NewAccessToken}
     */
    public function execute(User $user, array $deviceAttributes): array
    {
        return DB::transaction(function () use ($user, $deviceAttributes): array {
            $user = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $user->tokens()->delete();
            $user->devices()->whereNull('revoked_at')->update(['revoked_at' => now()]);

            $token = $user->createToken('device:'.$deviceAttributes['client_device_id'], ['api']);

            $device = $user->devices()->updateOrCreate(
                ['client_device_id' => $deviceAttributes['client_device_id']],
                [
                    'platform' => $deviceAttributes['platform'],
                    'app_version' => $deviceAttributes['app_version'] ?? null,
                    'personal_access_token_id' => $token->accessToken->getKey(),
                    'last_seen_at' => now(),
                    'revoked_at' => null,
                ],
            );

            return ['device' => $device, 'token' => $token];
        });
    }
}
