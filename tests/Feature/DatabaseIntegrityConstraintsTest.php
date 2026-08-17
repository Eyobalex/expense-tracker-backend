<?php

use App\Models\BudgetAdjustment;
use App\Models\BudgetPeriod;
use App\Models\Category;
use App\Models\ExchangeRate;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\IdempotencyOperation;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seedCurrencies();
});

test('postgresql rejects malformed financial and journal records at the database boundary', function (): void {
    $user = User::factory()->create(['base_currency_code' => 'ETB']);
    $account = FinancialAccount::factory()->for($user)->create(['currency_code' => 'ETB']);
    $transaction = FinancialTransaction::factory()->create([
        'user_id' => $user->id,
        'financial_account_id' => $account->id,
        'original_currency_code' => 'ETB',
    ]);
    $entry = JournalEntry::query()->create([
        'user_id' => $user->id,
        'financial_transaction_id' => $transaction->id,
        'type' => 'expense',
        'functional_currency_code' => 'ETB',
        'posted_at' => now(),
    ]);

    expect(fn (): bool => DB::table('financial_transactions')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'financial_account_id' => $account->id,
        'type' => 'expense',
        'state' => 'posted',
        'source' => 'manual',
        'occurred_at' => now(),
        'occurred_timezone' => 'Africa/Addis_Ababa',
        'original_amount_minor_units' => 100,
        'original_currency_code' => 'ETB',
        'base_amount_minor_units' => 100,
        'base_currency_code' => 'ETB',
        'version' => 1,
        'posted_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class)
        ->and(fn (): bool => DB::table('journal_lines')->insert([
            'id' => (string) Str::uuid(),
            'journal_entry_id' => $entry->id,
            'user_id' => $user->id,
            'ledger_code' => 'invalid.both_sides',
            'debit_minor_units' => 1,
            'credit_minor_units' => 1,
            'currency_code' => 'ETB',
            'base_debit_minor_units' => 1,
            'base_credit_minor_units' => 1,
            'base_currency_code' => 'ETB',
            'sequence' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
});

test('postgresql enforces budget borrowing and exchange-rate uniqueness invariants', function (): void {
    $user = User::factory()->create(['base_currency_code' => 'ETB']);
    $category = Category::factory()->for($user)->create(['kind' => 'expense']);
    $sourcePeriod = BudgetPeriod::factory()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'period_year' => 2026,
        'period_month' => 8,
    ]);
    $targetPeriod = BudgetPeriod::factory()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'period_year' => 2026,
        'period_month' => 9,
        'period_start_at' => now('UTC')->setDate(2026, 9, 1)->startOfDay(),
        'period_end_at' => now('UTC')->setDate(2026, 10, 1)->startOfDay(),
    ]);
    BudgetAdjustment::query()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'budget_period_id' => $sourcePeriod->id,
        'target_period_id' => $targetPeriod->id,
        'type' => 'borrowing_reserved',
        'amount_minor_units' => -1000,
        'currency_code' => 'ETB',
        'reason' => 'Feature 017 database constraint fixture.',
    ]);
    ExchangeRate::factory()->create([
        'base_currency_code' => 'USD',
        'quote_currency_code' => 'ETB',
        'rate_date' => '2026-08-17',
        'provider' => 'open_exchange_rates',
    ]);

    expect(fn (): BudgetPeriod => BudgetPeriod::factory()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'period_year' => 2026,
        'period_month' => 8,
    ]))->toThrow(QueryException::class)
        ->and(fn (): BudgetAdjustment => BudgetAdjustment::query()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'budget_period_id' => $sourcePeriod->id,
            'target_period_id' => $targetPeriod->id,
            'type' => 'borrowing_reserved',
            'amount_minor_units' => -1000,
            'currency_code' => 'ETB',
            'reason' => 'Duplicate borrowing reservation fixture.',
        ]))->toThrow(QueryException::class)
        ->and(fn (): ExchangeRate => ExchangeRate::factory()->create([
            'base_currency_code' => 'USD',
            'quote_currency_code' => 'ETB',
            'rate_date' => '2026-08-17',
            'provider' => 'open_exchange_rates',
        ]))->toThrow(QueryException::class);
});

test('posting retains the request and idempotency operation across the journal audit trail', function (): void {
    $user = User::factory()->create(['base_currency_code' => 'ETB']);
    $account = FinancialAccount::factory()->for($user)->create(['currency_code' => 'ETB']);
    $category = Category::factory()->for($user)->create(['kind' => 'expense']);
    $token = $user->createToken('constraint-trace', ['api'])->plainTextToken;

    $draft = $this->withToken($token)
        ->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/transactions', [
            'financial_account_id' => $account->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'occurred_at' => '2026-08-17T10:00:00+03:00',
            'occurred_timezone' => 'Africa/Addis_Ababa',
            'original_amount_minor_units' => 1000,
            'original_currency_code' => 'ETB',
            'description' => 'Correlation trace fixture.',
        ])
        ->assertCreated()
        ->json('data');
    $requestId = (string) Str::uuid();
    $idempotencyKey = (string) Str::uuid();

    $this->withToken($token)
        ->withHeaders(['If-Match' => '1', 'Idempotency-Key' => $idempotencyKey, 'X-Request-Id' => $requestId])
        ->postJson('/api/v1/transactions/'.$draft['id'].'/post')
        ->assertOk();

    $operation = IdempotencyOperation::query()->where('idempotency_key', $idempotencyKey)->firstOrFail();
    $posted = FinancialTransaction::query()->findOrFail($draft['id']);
    $entry = JournalEntry::query()->findOrFail($posted->journal_entry_id);
    $audit = DB::table('audit_events')->where('event_name', 'transaction.posted')->where('aggregate_id', $posted->id)->first();

    expect($entry->idempotency_operation_id)->toBe($operation->id)
        ->and($audit->request_id)->toBe($requestId)
        ->and($audit->operation_id)->toBe($operation->id)
        ->and(json_decode((string) $audit->summary, true, flags: JSON_THROW_ON_ERROR)['journal_entry_id'])->toBe($entry->id);
});

test('sync and insight reads have explicit per-user rate limits beyond the general API limit', function (): void {
    $user = User::factory()->create(['base_currency_code' => 'ETB']);
    $token = $user->createToken('rate-limit-fixture', ['api'])->plainTextToken;
    Config::set('sync.rate_limit_per_minute', 1);
    Config::set('insights.rate_limit_per_minute', 1);

    $this->withToken($token)->getJson('/api/v1/sync/pull?full=1')->assertOk();
    $this->withToken($token)->getJson('/api/v1/sync/pull?full=1')->assertTooManyRequests();
    $this->withToken($token)->getJson('/api/v1/insights/safe-to-spend?month=2026-08')->assertOk();
    $this->withToken($token)->getJson('/api/v1/insights/safe-to-spend?month=2026-08')->assertTooManyRequests();
});
