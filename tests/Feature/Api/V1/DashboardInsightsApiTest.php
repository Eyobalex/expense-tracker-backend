<?php

use App\Application\Budgeting\EnsureBudgetPeriodExists;
use App\Application\Insights\InsightSnapshotService;
use App\Domain\Insights\Enums\FormulaName;
use App\Domain\Shared\Time\Clock;
use App\Domain\Shared\Time\FrozenClock;
use App\Domain\Shared\Time\MonthlyPeriod;
use App\Models\Category;
use App\Models\FinancialAccount;
use App\Models\Item;
use App\Models\LineItem;
use App\Models\Merchant;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seedCurrencies();
    app()->instance(Clock::class, new FrozenClock(new DateTimeImmutable('2026-08-10 12:00:00 UTC')));
});

function insightUser(array $overrides = []): User
{
    return User::factory()->create(array_merge(['base_currency_code' => 'ETB', 'timezone' => 'Africa/Addis_Ababa', 'budget_timezone' => 'Africa/Addis_Ababa'], $overrides));
}

function insightToken(User $user): string
{
    return $user->createToken('insight-test', ['api'])->plainTextToken;
}

function insightCategory(User $user, string $name, string $behavior = 'variable', array $overrides = []): Category
{
    return Category::factory()->for($user)->create(array_merge([
        'name' => $name,
        'kind' => 'expense',
        'budget_enabled' => true,
        'forecast_behavior' => $behavior,
        'base_limit_minor_units' => 10000,
        'budget_currency_code' => 'ETB',
    ], $overrides));
}

function insightAccount(User $user): FinancialAccount
{
    return FinancialAccount::factory()->for($user)->create(['currency_code' => 'ETB']);
}

/** @return array<string, mixed> */
function postInsightTransaction(User $user, FinancialAccount $account, Category $category, int $amount, string $occurredAt, string $type = 'expense', array $extra = []): array
{
    $token = insightToken($user);
    $created = test()->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/transactions', array_merge([
        'financial_account_id' => $account->id,
        'category_id' => $category->id,
        'type' => $type,
        'occurred_at' => $occurredAt,
        'occurred_timezone' => 'Africa/Addis_Ababa',
        'original_amount_minor_units' => $amount,
        'original_currency_code' => 'ETB',
    ], $extra))->assertCreated()->json('data');

    return test()->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())->withHeader('If-Match', '1')->postJson("/api/v1/transactions/{$created['id']}/post")->assertOk()->json('data');
}

function insightPeriod(User $user, Category $category, string $month): void
{
    [$year, $monthNumber] = array_map('intval', explode('-', $month));
    app(EnsureBudgetPeriodExists::class)->forCategory($user, $category, MonthlyPeriod::forMonth($year, $monthNumber, $user->budget_timezone));
}

test('safe to spend uses authoritative effective budget limits and keeps overspent categories visible', function (): void {
    $user = insightUser();
    $food = insightCategory($user, 'Food', 'variable', ['base_limit_minor_units' => 10000]);
    $fun = insightCategory($user, 'Entertainment', 'variable', ['base_limit_minor_units' => 10000]);
    $account = insightAccount($user);
    postInsightTransaction($user, $account, $food, 2000, '2026-08-09T10:00:00Z');
    postInsightTransaction($user, $account, $fun, 12000, '2026-08-09T12:00:00Z');

    $response = $this->withToken(insightToken($user))->getJson('/api/v1/insights/safe-to-spend?month=2026-08')
        ->assertOk()
        ->assertJsonPath('data.formula.name', 'safe_to_spend')
        ->assertJsonPath('data.formula.version', 1)
        ->assertJsonPath('data.total_remaining_minor_units', 6000)
        ->assertJsonPath('data.value_minor_units', 273);

    expect(collect($response->json('data.categories'))->firstWhere('category_id', $fun->id)['status'])->toBe('overspent');
});

test('safe to spend is unavailable rather than zero with no active expense budgets', function (): void {
    $user = insightUser();

    $this->withToken(insightToken($user))->getJson('/api/v1/insights/safe-to-spend?month=2026-08')
        ->assertOk()
        ->assertJsonPath('data.status', 'not_available')
        ->assertJsonPath('data.reason', 'no_active_budgets')
        ->assertJsonPath('data.value_minor_units', null);
});

test('safe to spend uses one remaining day on the final calendar day', function (): void {
    app()->instance(Clock::class, new FrozenClock(new DateTimeImmutable('2026-08-31 12:00:00 UTC')));
    $user = insightUser(['budget_timezone' => 'UTC']);
    insightCategory($user, 'Food', 'variable', ['base_limit_minor_units' => 3100]);

    $this->withToken(insightToken($user))->getJson('/api/v1/insights/safe-to-spend?month=2026-08')
        ->assertOk()
        ->assertJsonPath('data.remaining_calendar_days_including_today', 1)
        ->assertJsonPath('data.value_minor_units', 3100);
});

test('fixed forecasting uses the median completed budget periods and never applies global daily extrapolation', function (): void {
    $user = insightUser();
    $rent = insightCategory($user, 'Rent', 'fixed');
    $groceries = insightCategory($user, 'Groceries', 'variable');
    $account = insightAccount($user);
    foreach (['2026-05-02', '2026-06-02', '2026-07-02'] as $date) {
        postInsightTransaction($user, $account, $rent, 20000, $date.'T10:00:00Z');
        insightPeriod($user, $rent, substr($date, 0, 7));
    }
    postInsightTransaction($user, $account, $rent, 20000, '2026-08-01T10:00:00Z');
    postInsightTransaction($user, $account, $groceries, 2000, '2026-08-01T10:00:00Z');

    $response = $this->withToken(insightToken($user))->getJson('/api/v1/insights/forecast?month=2026-08')->assertOk();

    expect(collect($response->json('data.categories'))->firstWhere('category_id', $rent->id))
        ->toMatchArray(['forecast_behavior' => 'fixed', 'current_actual_minor_units' => 20000, 'expected_remaining_minor_units' => 0, 'projected_amount_minor_units' => 20000]);
});

test('periodic forecasting groups same day purchases and predicts only future cadence dates in the period', function (): void {
    $user = insightUser();
    $groceries = insightCategory($user, 'Groceries', 'periodic');
    $account = insightAccount($user);
    foreach (['2026-07-18', '2026-07-25', '2026-08-01', '2026-08-08'] as $date) {
        postInsightTransaction($user, $account, $groceries, 1000, $date.'T10:00:00Z');
    }
    postInsightTransaction($user, $account, $groceries, 500, '2026-08-08T15:00:00Z');

    $category = $this->withToken(insightToken($user))->getJson('/api/v1/insights/forecast?month=2026-08')->assertOk()->json('data.categories.0');

    expect($category)->toMatchArray(['forecast_behavior' => 'periodic', 'method_used' => 'periodic', 'expected_remaining_minor_units' => 3000])
        ->and($category['history_window']['typical_interval_days'])->toBe(7)
        ->and($category['history_window']['expected_occurrence_count'])->toBe(3);
});

test('variable forecasting uses calendar days and excludes draft amounts', function (): void {
    $user = insightUser();
    $coffee = insightCategory($user, 'Coffee', 'variable');
    $account = insightAccount($user);
    postInsightTransaction($user, $account, $coffee, 3000, '2026-08-09T10:00:00Z');
    $this->withToken(insightToken($user))->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/transactions', [
        'financial_account_id' => $account->id, 'category_id' => $coffee->id, 'type' => 'expense', 'occurred_at' => '2026-08-10T10:00:00Z', 'occurred_timezone' => 'Africa/Addis_Ababa', 'original_amount_minor_units' => 9000, 'original_currency_code' => 'ETB',
    ])->assertCreated();

    $category = $this->withToken(insightToken($user))->getJson('/api/v1/insights/forecast?month=2026-08')->assertOk()->json('data.categories.0');

    expect($category['current_actual_minor_units'])->toBe(3000)
        ->and($category['history_window']['covered_calendar_days'])->toBe(1)
        ->and($category['expected_remaining_minor_units'])->toBe(31500);
});

test('income concentration is versioned, uses source shares, and suppresses classification when attribution is weak', function (): void {
    $user = insightUser();
    $income = Category::factory()->for($user)->create(['kind' => 'income', 'name' => 'Salary']);
    $account = insightAccount($user);
    postInsightTransaction($user, $account, $income, 8000, '2026-08-02T10:00:00Z', 'income', ['raw_merchant_text' => 'Employer A']);
    postInsightTransaction($user, $account, $income, 2000, '2026-08-03T10:00:00Z', 'income');

    $this->withToken(insightToken($user))->getJson('/api/v1/insights/income-concentration')
        ->assertOk()
        ->assertJsonPath('data.formula.name', 'income_concentration')
        ->assertJsonPath('data.total_qualifying_income_minor_units', 10000)
        ->assertJsonPath('data.unattributed_share', '0.200000000000')
        ->assertJsonPath('data.classification', 'concentrated');
});

test('income concentration is unavailable when no posted qualifying income exists', function (): void {
    $user = insightUser();

    $this->withToken(insightToken($user))->getJson('/api/v1/insights/income-concentration')
        ->assertOk()
        ->assertJsonPath('data.status', 'not_available')
        ->assertJsonPath('data.reason', 'no_qualifying_income')
        ->assertJsonPath('data.concentration_hhi', null);
});

test('item price movement compares compatible normalized receipt line purchases in the original currency', function (): void {
    $user = insightUser();
    $category = insightCategory($user, 'Food');
    $account = insightAccount($user);
    $item = Item::factory()->for($user)->create();
    $merchant = Merchant::factory()->for($user)->create();
    $previousTransaction = postInsightTransaction($user, $account, $category, 1000, '2026-08-02T10:00:00Z', 'expense', ['merchant_id' => $merchant->id]);
    $currentTransaction = postInsightTransaction($user, $account, $category, 1500, '2026-08-09T10:00:00Z', 'expense', ['merchant_id' => $merchant->id]);
    $receipt = Receipt::factory()->for($user)->create();
    LineItem::factory()->create(['user_id' => $user->id, 'receipt_id' => $receipt->id, 'financial_transaction_id' => $previousTransaction['id'], 'canonical_item_id' => $item->id, 'quantity' => '1.000000', 'unit_code' => 'kg', 'line_total_minor_units' => 1000, 'currency_code' => 'ETB', 'sequence' => 1]);
    $current = LineItem::factory()->create(['user_id' => $user->id, 'receipt_id' => $receipt->id, 'financial_transaction_id' => $currentTransaction['id'], 'canonical_item_id' => $item->id, 'quantity' => '1000.000000', 'unit_code' => 'g', 'line_total_minor_units' => 1500, 'currency_code' => 'ETB', 'sequence' => 2]);

    $this->withToken(insightToken($user))->getJson("/api/v1/insights/item-prices?line_item_id={$current->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'available')
        ->assertJsonPath('data.comparison_scope', 'same_merchant')
        ->assertJsonPath('data.percentage_change', '50.000000000000');
});

test('item price movement does not compare purchases in different original currencies', function (): void {
    $user = insightUser();
    $category = insightCategory($user, 'Food');
    $account = insightAccount($user);
    $item = Item::factory()->for($user)->create();
    $previousTransaction = postInsightTransaction($user, $account, $category, 1000, '2026-08-02T10:00:00Z');
    $currentTransaction = postInsightTransaction($user, $account, $category, 1500, '2026-08-09T10:00:00Z');
    $receipt = Receipt::factory()->for($user)->create();
    LineItem::factory()->create(['user_id' => $user->id, 'receipt_id' => $receipt->id, 'financial_transaction_id' => $previousTransaction['id'], 'canonical_item_id' => $item->id, 'quantity' => '1.000000', 'unit_code' => 'kg', 'line_total_minor_units' => 1000, 'currency_code' => 'USD', 'sequence' => 1]);
    $current = LineItem::factory()->create(['user_id' => $user->id, 'receipt_id' => $receipt->id, 'financial_transaction_id' => $currentTransaction['id'], 'canonical_item_id' => $item->id, 'quantity' => '1.000000', 'unit_code' => 'kg', 'line_total_minor_units' => 1500, 'currency_code' => 'ETB', 'sequence' => 2]);

    $this->withToken(insightToken($user))->getJson("/api/v1/insights/item-prices?line_item_id={$current->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'insufficient_history');
});

test('dashboard composes user-scoped live read models without persisting a snapshot on read', function (): void {
    $user = insightUser();
    $category = insightCategory($user, 'Food');
    $account = insightAccount($user);
    postInsightTransaction($user, $account, $category, 1000, '2026-08-09T10:00:00Z');

    $this->withToken(insightToken($user))->getJson('/api/v1/dashboard?month=2026-08')
        ->assertOk()
        ->assertJsonPath('data.base_currency_code', 'ETB')
        ->assertJsonPath('data.top_spending_categories.0.category_id', $category->id)
        ->assertJsonPath('data.safe_to_spend.formula.name', 'safe_to_spend')
        ->assertJsonPath('data.projected_month_end.formula.name', 'category_aware_projected_spend');

    $this->assertDatabaseCount('insight_snapshots', 0);
});

test('insight snapshots retain the original formula identity and inputs without affecting live dashboard reads', function (): void {
    $user = insightUser();
    $snapshot = app(InsightSnapshotService::class)->persist($user, FormulaName::SafeToSpend, $user->budget_timezone, 'ETB', new DateTimeImmutable('2026-08-10T12:00:00Z'), ['total_remaining_minor_units' => 1000], ['value_minor_units' => 45]);

    expect($snapshot->formula_name)->toBe('safe_to_spend')
        ->and($snapshot->formula_version)->toBe(1)
        ->and($snapshot->inputs)->toBe(['total_remaining_minor_units' => 1000]);
});

test('insight endpoints require authentication and conceal another users receipt line', function (): void {
    $owner = insightUser();
    $other = insightUser();
    $line = LineItem::factory()->for($owner)->create();

    $this->getJson('/api/v1/insights/forecast')->assertUnauthorized();
    $this->withToken(insightToken($other))->getJson("/api/v1/insights/item-prices?line_item_id={$line->id}")
        ->assertNotFound()->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
});
