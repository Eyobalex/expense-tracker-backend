<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class OnboardingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboarding_sets_financial_preferences_and_idempotently_seeds_starter_data(): void
    {
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
    }

    public function test_base_currency_cannot_change_after_posted_history_lock_exists(): void
    {
        $user = User::factory()->create(['base_currency_code' => 'ETB']);
        DB::table('financial_history_locks')->insert(['user_id' => $user->id, 'financial_account_id' => null, 'scope' => 'base_currency', 'first_posted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $token = $user->createToken('test', ['api'])->plainTextToken;

        $this->withToken($token)->withHeader('If-Match', '1')->withHeader('Idempotency-Key', (string) Str::uuid())
            ->putJson('/api/v1/onboarding', ['base_currency_code' => 'USD', 'timezone' => 'UTC', 'budget_timezone' => 'UTC'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'BASE_CURRENCY_LOCKED');
    }
}
