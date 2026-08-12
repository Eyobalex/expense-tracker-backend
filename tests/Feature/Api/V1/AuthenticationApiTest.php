<?php

namespace Tests\Feature\Api\V1;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthenticationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_uses_the_versioned_json_envelope(): void
    {
        $response = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'ada@example.test')
            ->assertJsonPath('data.version', 1)
            ->assertHeader('X-Request-Id');
        $this->assertDatabaseHas('users', ['email' => 'ada@example.test']);
    }

    public function test_validation_failures_use_the_stable_error_envelope(): void
    {
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/auth/register', [])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['fields' => ['name', 'email', 'password']]]);
    }

    public function test_registration_retry_replays_the_original_response_without_creating_another_user(): void
    {
        $key = (string) Str::uuid();
        $payload = [
            'name' => 'Grace Hopper',
            'email' => 'grace@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ];

        $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/auth/register', $payload)->assertCreated();
        $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/auth/register', $payload)
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_a_new_device_login_revokes_old_tokens_and_marks_the_old_device_revoked(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $firstDeviceId = (string) Str::uuid();
        $secondDeviceId = (string) Str::uuid();

        $firstLogin = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/auth/login', $this->loginPayload($user, $firstDeviceId));
        $firstLogin->assertOk();
        $firstToken = $firstLogin->json('data.token');

        $secondLogin = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/auth/login', $this->loginPayload($user, $secondDeviceId));
        $secondLogin->assertOk();

        $this->withToken($firstToken)->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->assertNotNull(Device::query()->where('client_device_id', $firstDeviceId)->value('revoked_at'));
        $this->assertNull(Device::query()->where('client_device_id', $secondDeviceId)->value('revoked_at'));
    }

    public function test_invalid_credentials_and_protected_routes_return_the_api_error_envelope(): void
    {
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/auth/login', [
            'email' => 'missing@example.test',
            'password' => 'password',
            'client_device_id' => (string) Str::uuid(),
            'platform' => 'android',
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');

        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED')
            ->assertHeader('X-Request-Id');
    }

    public function test_authentication_endpoints_are_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/auth/login', [
                'email' => 'rate-limit@example.test',
                'password' => 'password',
                'client_device_id' => (string) Str::uuid(),
                'platform' => 'android',
            ])->assertUnprocessable();
        }

        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/auth/login', [
            'email' => 'rate-limit@example.test',
            'password' => 'password',
            'client_device_id' => (string) Str::uuid(),
            'platform' => 'android',
        ])->assertTooManyRequests();
    }

    public function test_password_reset_link_is_non_enumerating_and_invalid_reset_cannot_change_a_password(): void
    {
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/auth/forgot-password', ['email' => 'missing@example.test'])
            ->assertAccepted()
            ->assertJsonPath('data.accepted', true);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/auth/reset-password', [
                'email' => 'missing@example.test',
                'token' => 'invalid',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'PASSWORD_RESET_FAILED');
    }

    /**
     * @return array<string, string>
     */
    private function loginPayload(User $user, string $deviceId): array
    {
        return [
            'email' => $user->email,
            'password' => 'password',
            'client_device_id' => $deviceId,
            'platform' => 'android',
            'app_version' => '1.0.0',
        ];
    }
}
