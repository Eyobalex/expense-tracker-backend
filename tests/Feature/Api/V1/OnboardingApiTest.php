<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);
beforeEach(function (): void {
    $this->seedCurrencies();
});

test('onboarding sets financial preferences and idempotently seeds starter data', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['api'])->plainTextToken;
    $key = (string) Str::uuid();
    $payload = ['base_currency_code' => 'ETB', 'timezone' => 'Africa/Addis_Ababa', 'budget_timezone' => 'Africa/Addis_Ababa', 'seed_starter_data' => true];

    $this->withToken($token)->withHeader('If-Match', '1')->withHeader('Idempotency-Key', $key)
        ->putJson('/api/v1/onboarding', $payload)
        ->assertOk()->assertJsonPath('data.base_currency_code', 'ETB')->assertJsonPath('data.onboarding_completed', true);
    $this->withToken($token)->withHeader('If-Match', '1')->withHeader('Idempotency-Key', $key)
        ->putJson('/api/v1/onboarding', $payload)
        ->assertOk()->assertHeader('Idempotency-Replayed', 'true');

    $this->assertDatabaseCount('financial_accounts', 1);
    $this->assertDatabaseCount('categories', 5);
    $this->assertDatabaseCount('ledger_account_mappings', 6);
});

test('base currency cannot change after posted history lock exists', function (): void {
    $user = User::factory()->create(['base_currency_code' => 'ETB']);
    DB::table('financial_history_locks')->insert(['user_id' => $user->id, 'financial_account_id' => null, 'scope' => 'base_currency', 'first_posted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    $token = $user->createToken('test', ['api'])->plainTextToken;

    $this->withToken($token)->withHeader('If-Match', '1')->withHeader('Idempotency-Key', (string) Str::uuid())
        ->putJson('/api/v1/onboarding', ['base_currency_code' => 'USD', 'timezone' => 'UTC', 'budget_timezone' => 'UTC'])
        ->assertUnprocessable()->assertJsonPath('error.code', 'BASE_CURRENCY_LOCKED');
});
