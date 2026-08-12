<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Identity\LoginUserAction;
use App\Actions\Identity\RegisterUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\PasswordResetLinkRequest;
use App\Http\Requests\Api\V1\RefreshOrRevokeRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Requests\Api\V1\ResetPasswordRequest;
use App\Http\Resources\Api\V1\DeviceResource;
use App\Http\Resources\Api\V1\ProfileResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterUserAction $registerUser): JsonResponse
    {
        $user = $registerUser->execute($request->validated());

        return $this->success($request, (new ProfileResource($user))->resolve($request), JsonResponse::HTTP_CREATED);
    }

    public function login(LoginRequest $request, LoginUserAction $loginUser): JsonResponse
    {
        $attributes = $request->validated();
        $user = User::query()->where('email', mb_strtolower($attributes['email']))->first();

        if ($user === null || ! Hash::check($attributes['password'], $user->password)) {
            return $this->error($request, 'INVALID_CREDENTIALS', 'The provided credentials are incorrect.', JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $session = $loginUser->execute($user, $attributes);

        return $this->success($request, [
            'token' => $session['token']->plainTextToken,
            'token_type' => 'Bearer',
            'user' => (new ProfileResource($user->fresh()))->resolve($request),
            'device' => (new DeviceResource($session['device']))->resolve($request),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token !== null) {
            $request->user()->devices()->where('personal_access_token_id', $token->getKey())->update(['revoked_at' => now()]);
            $token->delete();
        }

        return $this->success($request, ['revoked' => true]);
    }

    public function refreshOrRevoke(RefreshOrRevokeRequest $request, LoginUserAction $loginUser): JsonResponse
    {
        $action = $request->validated('action');
        $user = $request->user();

        if ($action === 'revoke') {
            $user->tokens()->delete();
            $user->devices()->whereNull('revoked_at')->update(['revoked_at' => now()]);

            return $this->success($request, ['revoked' => true]);
        }

        $currentDevice = $user->devices()
            ->where('personal_access_token_id', $user->currentAccessToken()?->getKey())
            ->first();

        if ($currentDevice === null) {
            return $this->error($request, 'DEVICE_SESSION_NOT_FOUND', 'The active device session was not found.', JsonResponse::HTTP_UNAUTHORIZED);
        }

        $session = $loginUser->execute($user, [
            'client_device_id' => $currentDevice->client_device_id,
            'platform' => $currentDevice->platform,
            'app_version' => $currentDevice->app_version,
        ]);

        return $this->success($request, [
            'token' => $session['token']->plainTextToken,
            'token_type' => 'Bearer',
            'device' => (new DeviceResource($session['device']))->resolve($request),
        ]);
    }

    public function sendPasswordResetLink(PasswordResetLinkRequest $request): JsonResponse
    {
        Password::sendResetLink($request->validated());

        return $this->success($request, ['accepted' => true], JsonResponse::HTTP_ACCEPTED);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $attributes = $request->validated();
        $status = Password::reset($attributes, function (User $user, string $password): void {
            $user->forceFill(['password' => $password])->save();
            $user->tokens()->delete();
            $user->devices()->whereNull('revoked_at')->update(['revoked_at' => now()]);
        });

        if ($status !== Password::PASSWORD_RESET) {
            return $this->error($request, 'PASSWORD_RESET_FAILED', __($status), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->success($request, ['reset' => true]);
    }
}
