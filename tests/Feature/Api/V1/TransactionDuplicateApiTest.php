<?php

use App\Models\Category;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\Receipt;
use App\Models\TransactionDuplicateCandidate;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seedCurrencies();
});

function duplicateToken(User $user): string
{
    return $user->createToken('duplicate-test', ['api'])->plainTextToken;
}

function duplicatePayload(FinancialAccount $account, Category $category, array $overrides = []): array
{
    return array_merge([
        'financial_account_id' => $account->id,
        'category_id' => $category->id,
        'type' => 'expense',
        'occurred_at' => '2026-08-14T10:00:00+03:00',
        'occurred_timezone' => 'Africa/Addis_Ababa',
        'original_amount_minor_units' => 1250,
        'original_currency_code' => $account->currency_code,
        'raw_merchant_text' => 'Market One',
        'description' => 'Receipt import',
    ], $overrides);
}

function createDuplicateTransaction($test, string $token, FinancialAccount $account, Category $category, array $overrides = []): array
{
    return $test->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/transactions', duplicatePayload($account, $category, $overrides))
        ->assertCreated()->json('data');
}

test('a matching imported transaction returns a versioned weighted duplicate warning', function (): void {
    $user = User::factory()->create(['base_currency_code' => 'ETB']);
    $account = FinancialAccount::factory()->for($user)->create(['currency_code' => 'ETB']);
    $category = Category::factory()->for($user)->create(['kind' => 'expense']);
    $token = duplicateToken($user);
    createDuplicateTransaction($this, $token, $account, $category, ['reference_number' => 'TX-123']);
    $source = createDuplicateTransaction($this, $token, $account, $category, ['reference_number' => 'TX-123', 'occurred_at' => '2026-08-14T10:04:00+03:00']);

    $this->withToken($token)->getJson("/api/v1/transactions/{$source['id']}/duplicates")
        ->assertOk()
        ->assertJsonCount(1, 'data.duplicates')
        ->assertJsonPath('data.duplicates.0.algorithm_version', 'duplicate-score-v1')
        ->assertJsonPath('data.duplicates.0.candidate_transaction.reference_number', 'TX-123');
});

test('keeping both resolves the warning and permits the normal posting path', function (): void {
    $user = User::factory()->create(['base_currency_code' => 'ETB']);
    $account = FinancialAccount::factory()->for($user)->create(['currency_code' => 'ETB']);
    $category = Category::factory()->for($user)->create(['kind' => 'expense']);
    $token = duplicateToken($user);
    createDuplicateTransaction($this, $token, $account, $category);
    $source = createDuplicateTransaction($this, $token, $account, $category, ['occurred_at' => '2026-08-14T10:04:00+03:00']);
    $candidate = TransactionDuplicateCandidate::query()->where('financial_transaction_id', $source['id'])->sole();

    $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/v1/transactions/{$source['id']}/duplicate-decision", ['candidate_id' => $candidate->id, 'decision' => 'keep_both'])
        ->assertOk()->assertJsonPath('data.status', 'keep_both');

    $this->withToken($token)->withHeader('If-Match', '1')->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/v1/transactions/{$source['id']}/post")
        ->assertOk()->assertJsonPath('data.state', 'posted');
});

test('an amount-only match across a different account, merchant, and month is not a duplicate warning', function (): void {
    $user = User::factory()->create(['base_currency_code' => 'ETB']);
    $firstAccount = FinancialAccount::factory()->for($user)->create(['currency_code' => 'ETB']);
    $secondAccount = FinancialAccount::factory()->for($user)->create(['currency_code' => 'ETB']);
    $category = Category::factory()->for($user)->create(['kind' => 'expense']);
    $token = duplicateToken($user);
    createDuplicateTransaction($this, $token, $firstAccount, $category, ['occurred_at' => '2026-07-01T10:00:00+03:00', 'raw_merchant_text' => 'Market One']);
    $source = createDuplicateTransaction($this, $token, $secondAccount, $category, ['occurred_at' => '2026-08-14T10:00:00+03:00', 'raw_merchant_text' => 'Market Two']);

    $this->withToken($token)->getJson("/api/v1/transactions/{$source['id']}/duplicates")
        ->assertOk()->assertJsonCount(0, 'data.duplicates');
});

test('replacing an unposted candidate retains it as a cancelled auditable record', function (): void {
    $user = User::factory()->create(['base_currency_code' => 'ETB']);
    $account = FinancialAccount::factory()->for($user)->create(['currency_code' => 'ETB']);
    $category = Category::factory()->for($user)->create(['kind' => 'expense']);
    $token = duplicateToken($user);
    $existing = createDuplicateTransaction($this, $token, $account, $category);
    $source = createDuplicateTransaction($this, $token, $account, $category, ['occurred_at' => '2026-08-14T10:04:00+03:00']);
    $candidate = TransactionDuplicateCandidate::query()->where('financial_transaction_id', $source['id'])->sole();

    $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/v1/transactions/{$source['id']}/duplicate-decision", ['candidate_id' => $candidate->id, 'decision' => 'replace_pending_draft'])
        ->assertOk()->assertJsonPath('data.status', 'replaced');

    $this->assertDatabaseHas('financial_transactions', ['id' => $existing['id'], 'state' => 'cancelled', 'duplicate_replaced_by_id' => $source['id']]);
    $this->assertDatabaseHas('audit_events', ['event_name' => 'transaction.duplicate_decision_recorded', 'aggregate_id' => $source['id']]);
});

test('cancelling an OCR import preserves its receipt evidence and blocks subsequent posting', function (): void {
    $user = User::factory()->create(['base_currency_code' => 'ETB']);
    $account = FinancialAccount::factory()->for($user)->create(['currency_code' => 'ETB']);
    $category = Category::factory()->for($user)->create(['kind' => 'expense']);
    $token = duplicateToken($user);
    createDuplicateTransaction($this, $token, $account, $category);
    $source = createDuplicateTransaction($this, $token, $account, $category, ['occurred_at' => '2026-08-14T10:04:00+03:00']);
    $receipt = Receipt::factory()->for($user)->create(['review_transaction_id' => $source['id']]);
    $candidate = TransactionDuplicateCandidate::query()->where('financial_transaction_id', $source['id'])->sole();

    $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/v1/transactions/{$source['id']}/duplicate-decision", ['candidate_id' => $candidate->id, 'decision' => 'cancel_current_import'])
        ->assertOk()->assertJsonPath('data.status', 'cancelled');

    expect(FinancialTransaction::query()->findOrFail($source['id'])->state)->toBe('cancelled')
        ->and(Receipt::query()->findOrFail($receipt->id)->review_transaction_id)->toBe($source['id']);
    $this->withToken($token)->withHeader('If-Match', '2')->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/v1/transactions/{$source['id']}/post")
        ->assertNotFound();
});

test('a posted candidate cannot be replaced and another user cannot inspect duplicate candidates', function (): void {
    $user = User::factory()->create(['base_currency_code' => 'ETB']);
    $otherUser = User::factory()->create(['base_currency_code' => 'ETB']);
    $account = FinancialAccount::factory()->for($user)->create(['currency_code' => 'ETB']);
    $category = Category::factory()->for($user)->create(['kind' => 'expense']);
    $token = duplicateToken($user);
    $existing = createDuplicateTransaction($this, $token, $account, $category);
    $this->withToken($token)->withHeader('If-Match', '1')->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/api/v1/transactions/{$existing['id']}/post")->assertOk();
    $source = createDuplicateTransaction($this, $token, $account, $category, ['occurred_at' => '2026-08-14T10:04:00+03:00']);
    $candidate = TransactionDuplicateCandidate::query()->where('financial_transaction_id', $source['id'])->sole();

    $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/v1/transactions/{$source['id']}/duplicate-decision", ['candidate_id' => $candidate->id, 'decision' => 'replace_pending_draft'])
        ->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_STATE_TRANSITION');
    $this->withToken(duplicateToken($otherUser))->getJson("/api/v1/transactions/{$source['id']}/duplicates")->assertNotFound();
});

test('postgresql rejects a duplicate candidate that references the same transaction on both sides', function (): void {
    $user = User::factory()->create(['base_currency_code' => 'ETB']);
    $account = FinancialAccount::factory()->for($user)->create(['currency_code' => 'ETB']);
    $category = Category::factory()->for($user)->create(['kind' => 'expense']);
    $transaction = FinancialTransaction::factory()->for($user)->for($account, 'financialAccount')->for($category)->create(['original_currency_code' => 'ETB']);

    expect(fn (): bool => DB::table('transaction_duplicate_candidates')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'financial_transaction_id' => $transaction->id,
        'candidate_transaction_id' => $transaction->id,
        'score' => '0.700000',
        'score_breakdown' => json_encode(['amount' => 0.3], JSON_THROW_ON_ERROR),
        'algorithm_version' => 'duplicate-score-v1',
        'status' => 'suggested',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
