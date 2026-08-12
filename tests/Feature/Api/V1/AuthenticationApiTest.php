<?php

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);
test('registration uses the versioned json envelope', function (): void {
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
});

test('validation failures use the stable error envelope', function (): void {
    $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/auth/register', [])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_FAILED')
        ->assertJsonStructure(['error' => ['fields' => ['name', 'email', 'password']]]);
});

test('registration retry replays the original response without creating another user', function (): void {
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
});

test('a new device login revokes old tokens and marks the old device revoked', function (): void {
    $user = User::factory()->create(['password' => Hash::make('password')]);
    $firstDeviceId = (string) Str::uuid();
    $secondDeviceId = (string) Str::uuid();

    $firstLogin = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/auth/login', loginPayload($user, $firstDeviceId));
    $firstLogin->assertOk();
    $firstToken = $firstLogin->json('data.token');

    $secondLogin = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/auth/login', loginPayload($user, $secondDeviceId));
    $secondLogin->assertOk();

    $this->withToken($firstToken)->getJson('/api/v1/me')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'UNAUTHENTICATED');

    $this->assertNotNull(Device::query()->where('client_device_id', $firstDeviceId)->value('revoked_at'));
    $this->assertNull(Device::query()->where('client_device_id', $secondDeviceId)->value('revoked_at'));
});

test('invalid credentials and protected routes return the api error envelope', function (): void {
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
});

test('authentication endpoints are rate limited', function (): void {
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
});

test('password reset link is non enumerating and invalid reset cannot change a password', function (): void {
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
});

/**
 * @return array<string, string>
 */
function loginPayload(User $user, string $deviceId): array
{
    return [
        'email' => $user->email,
        'password' => 'password',
        'client_device_id' => $deviceId,
        'platform' => 'android',
        'app_version' => '1.0.0',
    ];
}
