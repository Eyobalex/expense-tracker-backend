<?php

use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);
beforeEach(function (): void {
    $this->seedCurrencies();
});

test('account creation rejects unsupported and inactive currencies', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['api'])->plainTextToken;

    $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/accounts', ['name' => 'Invalid', 'type' => 'cash', 'currency_code' => 'ZZZ'])
        ->assertUnprocessable()->assertJsonPath('error.code', 'UNSUPPORTED_CURRENCY');

    Currency::query()->whereKey('USD')->update(['is_active' => false]);

    $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/accounts', ['name' => 'Inactive', 'type' => 'cash', 'currency_code' => 'USD'])
        ->assertUnprocessable()->assertJsonPath('error.code', 'INACTIVE_CURRENCY');
});

test('authenticated users can list active supported currencies', function (): void {
    Currency::query()->whereKey('USD')->update(['is_active' => false]);
    $user = User::factory()->create();

    $this->withToken($user->createToken('test', ['api'])->plainTextToken)
        ->getJson('/api/v1/currencies')
        ->assertOk()->assertJsonPath('data.currencies.0.code', 'ETB')->assertJsonMissing(['code' => 'USD']);
});

test('onboarding rejects an unsupported base currency', function (): void {
    $user = User::factory()->create();

    $this->withToken($user->createToken('test', ['api'])->plainTextToken)
        ->withHeader('If-Match', '1')->withHeader('Idempotency-Key', (string) Str::uuid())
        ->putJson('/api/v1/onboarding', ['base_currency_code' => 'ZZZ', 'timezone' => 'UTC', 'budget_timezone' => 'UTC'])
        ->assertUnprocessable()->assertJsonPath('error.code', 'UNSUPPORTED_CURRENCY');
});
