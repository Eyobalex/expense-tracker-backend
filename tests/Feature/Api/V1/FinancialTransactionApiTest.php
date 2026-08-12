<?php

use App\Models\Category;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\JournalLine;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seedCurrencies();
});

function transactionPayload(FinancialAccount $account, Category $category, array $overrides = []): array
{
    return array_merge([
        'financial_account_id' => $account->id,
        'category_id' => $category->id,
        'type' => 'expense',
        'occurred_at' => '2026-08-12T10:00:00+03:00',
        'occurred_timezone' => 'Africa/Addis_Ababa',
        'original_amount_minor_units' => 1250,
        'original_currency_code' => $account->currency_code,
        'description' => 'Market purchase',
    ], $overrides);
}

function apiToken(User $user): string
{
    return $user->createToken('ledger-test', ['api'])->plainTextToken;
}

test('an owner posts an expense as an atomic balanced journal and account balance projection', function (): void {
    $user = User::factory()->create(['base_currency_code' => 'ETB']);
    $account = FinancialAccount::factory()->for($user)->create(['currency_code' => 'ETB']);
    $category = Category::factory()->for($user)->create(['kind' => 'expense']);
    $token = apiToken($user);

    $draft = $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/transactions', transactionPayload($account, $category))
        ->assertCreated()->assertJsonPath('data.state', 'draft')->json('data');

    $this->withToken($token)->withHeader('If-Match', '1')->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/v1/transactions/{$draft['id']}/post")
        ->assertOk()->assertJsonPath('data.state', 'posted')->assertJsonPath('data.base_amount_minor_units', 1250);

    $lines = JournalLine::query()->where('user_id', $user->id)->get();
    expect($lines)->toHaveCount(2)
        ->and($lines->sum('base_debit_minor_units'))->toBe($lines->sum('base_credit_minor_units'));
    $this->assertDatabaseHas('financial_history_locks', ['user_id' => $user->id, 'financial_account_id' => $account->id]);

    $this->withToken($token)->getJson('/api/v1/transactions/balances')
        ->assertOk()->assertJsonPath('data.balances.0.account_id', $account->id)->assertJsonPath('data.balances.0.balance_minor_units', -1250);
});

test('posting retry is replayed and cannot create another journal', function (): void {
    $user = User::factory()->create(['base_currency_code' => 'ETB']);
    $account = FinancialAccount::factory()->for($user)->create(['currency_code' => 'ETB']);
    $category = Category::factory()->for($user)->create(['kind' => 'expense']);
    $token = apiToken($user);
    $draft = $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/transactions', transactionPayload($account, $category))->json('data');
    $key = (string) Str::uuid();

    $this->withToken($token)->withHeader('If-Match', '1')->withHeader('Idempotency-Key', $key)->postJson("/api/v1/transactions/{$draft['id']}/post")->assertOk();
    $this->withToken($token)->withHeader('If-Match', '1')->withHeader('Idempotency-Key', $key)->postJson("/api/v1/transactions/{$draft['id']}/post")->assertOk()->assertHeader('Idempotency-Replayed', 'true');

    $this->assertDatabaseCount('journal_entries', 1);
    $this->assertDatabaseCount('journal_lines', 2);
});

test('credit-card repayment is a liability transfer and never creates an expense journal line', function (): void {
    $user = User::factory()->create(['base_currency_code' => 'ETB']);
    $card = FinancialAccount::factory()->for($user)->create(['type' => 'credit_card', 'accounting_type' => 'liability', 'currency_code' => 'ETB']);
    $bank = FinancialAccount::factory()->for($user)->create(['type' => 'bank', 'accounting_type' => 'asset', 'currency_code' => 'ETB']);
    $category = Category::factory()->for($user)->create(['kind' => 'expense']);
    $token = apiToken($user);
    $draft = $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/transactions', transactionPayload($card, $category, ['type' => 'credit_card_repayment', 'counterparty_account_id' => $bank->id]))->assertCreated()->json('data');

    $this->withToken($token)->withHeader('If-Match', '1')->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/api/v1/transactions/{$draft['id']}/post")->assertOk();

    expect(JournalLine::query()->where('ledger_code', 'like', 'expense.%')->exists())->toBeFalse();
});

test('a posted transaction is reversed by equal and opposite immutable journal lines', function (): void {
    $user = User::factory()->create(['base_currency_code' => 'ETB']);
    $account = FinancialAccount::factory()->for($user)->create(['currency_code' => 'ETB']);
    $category = Category::factory()->for($user)->create(['kind' => 'expense']);
    $token = apiToken($user);
    $draft = $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/transactions', transactionPayload($account, $category))->json('data');
    $posted = $this->withToken($token)->withHeader('If-Match', '1')->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/api/v1/transactions/{$draft['id']}/post")->json('data');

    $this->withToken($token)->withHeader('If-Match', (string) $posted['version'])->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/v1/transactions/{$posted['id']}/reverse", ['reason' => 'Entered twice'])
        ->assertCreated()->assertJsonPath('data.state', 'reversed')->assertJsonPath('data.reversal_of_id', $posted['id']);

    expect(JournalLine::query()->sum('base_debit_minor_units'))->toBe(JournalLine::query()->sum('base_credit_minor_units'));
    $this->assertDatabaseHas('financial_transactions', ['id' => $posted['id'], 'state' => 'reversed']);
});

test('invalid split reconciliation and cross-user transactions are rejected', function (): void {
    $user = User::factory()->create(['base_currency_code' => 'ETB']);
    $account = FinancialAccount::factory()->for($user)->create(['currency_code' => 'ETB']);
    $category = Category::factory()->for($user)->create(['kind' => 'expense']);
    $otherAccount = FinancialAccount::factory()->create(['currency_code' => 'ETB']);
    $token = apiToken($user);

    $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/transactions', transactionPayload($account, $category, ['splits' => [['category_id' => $category->id, 'amount_minor_units' => 1000, 'currency_code' => 'ETB']]]))
        ->assertCreated()->json('data');
    $draft = FinancialTransaction::query()->latest('created_at')->firstOrFail();
    $this->withToken($token)->withHeader('If-Match', '1')->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/v1/transactions/{$draft->id}/post")->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_MONEY');

    $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/transactions', transactionPayload($otherAccount, $category))
        ->assertUnprocessable()->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
});

test('postgresql line constraints reject both-sided or zero journal lines', function (): void {
    $user = User::factory()->create();
    $this->seedCurrencies();

    expect(fn (): bool => DB::table('journal_lines')->insert([
        'id' => (string) Str::uuid(), 'journal_entry_id' => (string) Str::uuid(), 'user_id' => $user->id, 'ledger_code' => 'invalid',
        'debit_minor_units' => 1, 'credit_minor_units' => 1, 'currency_code' => 'ETB', 'base_debit_minor_units' => 1, 'base_credit_minor_units' => 1,
        'base_currency_code' => 'ETB', 'sequence' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('cross-currency transfers preserve native amounts and balance with an explicit FX rounding line', function (): void {
    $user = User::factory()->create(['base_currency_code' => 'ETB']);
    $source = FinancialAccount::factory()->for($user)->create(['currency_code' => 'USD']);
    $destination = FinancialAccount::factory()->for($user)->create(['currency_code' => 'ETB']);
    $category = Category::factory()->for($user)->create(['kind' => 'expense']);
    $token = apiToken($user);

    $draft = $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/transactions', transactionPayload($source, $category, [
        'type' => 'transfer', 'counterparty_account_id' => $destination->id, 'original_amount_minor_units' => 10000,
        'counterparty_amount_minor_units' => 550001, 'counterparty_currency_code' => 'ETB', 'used_rate' => '55',
        'reference_rate' => '55', 'rate_date' => '2026-08-12', 'rate_source' => 'fixture', 'rounding_mode' => 'HALF_EVEN',
    ]))->assertCreated()->json('data');

    $this->withToken($token)->withHeader('If-Match', '1')->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/api/v1/transactions/{$draft['id']}/post")->assertOk();

    $lines = JournalLine::query()->where('user_id', $user->id)->get();
    expect($lines->sum('base_debit_minor_units'))->toBe($lines->sum('base_credit_minor_units'))
        ->and($lines->where('ledger_code', 'fx.rounding'))->toHaveCount(1)
        ->and($lines->where('financial_account_id', $source->id)->first()->currency_code)->toBe('USD')
        ->and($lines->where('financial_account_id', $destination->id)->first()->currency_code)->toBe('ETB');
});

test('refunds link to posted expenses and reduce the expense ledger instead of becoming income', function (): void {
    $user = User::factory()->create(['base_currency_code' => 'ETB']);
    $account = FinancialAccount::factory()->for($user)->create(['currency_code' => 'ETB']);
    $category = Category::factory()->for($user)->create(['kind' => 'expense']);
    $token = apiToken($user);
    $expense = $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/transactions', transactionPayload($account, $category))->json('data');
    $expense = $this->withToken($token)->withHeader('If-Match', '1')->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/api/v1/transactions/{$expense['id']}/post")->json('data');
    $refund = $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/transactions', transactionPayload($account, $category, ['type' => 'refund', 'related_transaction_id' => $expense['id'], 'original_amount_minor_units' => 500]))->json('data');

    $this->withToken($token)->withHeader('If-Match', '1')->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/api/v1/transactions/{$refund['id']}/post")->assertOk();

    expect(JournalLine::query()->where('ledger_code', 'like', 'income.%')->exists())->toBeFalse()
        ->and(JournalLine::query()->where('ledger_code', 'like', 'expense.category.%')->sum('debit_minor_units'))->toBe(1250)
        ->and(JournalLine::query()->where('ledger_code', 'like', 'expense.category.%')->sum('credit_minor_units'))->toBe(500);
});

test('manual adjustments require the explicit balance-correction subtype and reason', function (): void {
    $user = User::factory()->create(['base_currency_code' => 'ETB']);
    $account = FinancialAccount::factory()->for($user)->create(['currency_code' => 'ETB']);
    $category = Category::factory()->for($user)->create(['kind' => 'expense']);
    $token = apiToken($user);

    $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/transactions', transactionPayload($account, $category, ['type' => 'adjustment']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['adjustment_subtype', 'adjustment_direction', 'reason']);
});
