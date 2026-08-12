<?php

namespace Tests\Feature\Api\V1;

use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CurrencyRegistryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCurrencies();
    }

    public function test_account_creation_rejects_unsupported_and_inactive_currencies(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test', ['api'])->plainTextToken;

        $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/accounts', ['name' => 'Invalid', 'type' => 'cash', 'currency_code' => 'ZZZ'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'UNSUPPORTED_CURRENCY');

        Currency::query()->whereKey('USD')->update(['is_active' => false]);

        $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/accounts', ['name' => 'Inactive', 'type' => 'cash', 'currency_code' => 'USD'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'INACTIVE_CURRENCY');
    }

    public function test_authenticated_users_can_list_active_supported_currencies(): void
    {
        Currency::query()->whereKey('USD')->update(['is_active' => false]);
        $user = User::factory()->create();

        $this->withToken($user->createToken('test', ['api'])->plainTextToken)
            ->getJson('/api/v1/currencies')
            ->assertOk()->assertJsonPath('data.currencies.0.code', 'ETB')->assertJsonMissing(['code' => 'USD']);
    }

    public function test_onboarding_rejects_an_unsupported_base_currency(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('test', ['api'])->plainTextToken)
            ->withHeader('If-Match', '1')->withHeader('Idempotency-Key', (string) Str::uuid())
            ->putJson('/api/v1/onboarding', ['base_currency_code' => 'ZZZ', 'timezone' => 'UTC', 'budget_timezone' => 'UTC'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'UNSUPPORTED_CURRENCY');
    }
}
