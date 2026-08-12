<?php

namespace Tests\Feature\Api\V1;

use App\Models\FinancialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinancialAccountApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCurrencies();
    }

    public function test_owner_can_create_archive_and_restore_an_account_with_idempotency_and_versions(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test', ['api'])->plainTextToken;
        $key = (string) Str::uuid();
        $payload = ['name' => 'Main cash', 'type' => 'cash', 'currency_code' => 'ETB', 'opening_balance_configured' => true];

        $response = $this->withToken($token)->withHeader('Idempotency-Key', $key)->postJson('/api/v1/accounts', $payload);
        $response->assertCreated()->assertJsonPath('data.currency_code', 'ETB')->assertJsonPath('data.version', 1);
        $accountId = $response->json('data.id');

        $this->withToken($token)->withHeader('Idempotency-Key', $key)->postJson('/api/v1/accounts', $payload)
            ->assertCreated()->assertHeader('Idempotency-Replayed', 'true');
        $this->assertDatabaseCount('financial_accounts', 1);

        $this->withToken($token)->withHeader('If-Match', '1')->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/v1/accounts/{$accountId}/archive")
            ->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.archived_at', fn (?string $value): bool => $value !== null);

        $this->withToken($token)->withHeader('If-Match', '2')->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/v1/accounts/{$accountId}/restore")
            ->assertOk()->assertJsonPath('data.version', 3)->assertJsonPath('data.archived_at', null);
    }

    public function test_account_currency_cannot_change_after_posted_history_lock_exists(): void
    {
        $user = User::factory()->create();
        $account = FinancialAccount::factory()->for($user)->create(['currency_code' => 'ETB']);
        DB::table('financial_history_locks')->insert(['user_id' => $user->id, 'financial_account_id' => $account->id, 'scope' => 'account_currency', 'first_posted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $token = $user->createToken('test', ['api'])->plainTextToken;

        $this->withToken($token)->withHeader('If-Match', '1')->withHeader('Idempotency-Key', (string) Str::uuid())
            ->patchJson("/api/v1/accounts/{$account->id}", ['currency_code' => 'USD'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'ACCOUNT_CURRENCY_LOCKED');
    }

    public function test_another_user_cannot_view_an_account(): void
    {
        $account = FinancialAccount::factory()->create();
        $other = User::factory()->create();

        $this->withToken($other->createToken('test', ['api'])->plainTextToken)
            ->getJson("/api/v1/accounts/{$account->id}")
            ->assertNotFound()->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    }
}
