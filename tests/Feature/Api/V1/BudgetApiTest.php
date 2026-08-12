<?php

use App\Application\Budgeting\EnsureBudgetPeriodExists;
use App\Domain\Shared\Time\MonthlyPeriod;
use App\Models\BudgetAdjustment;
use App\Models\BudgetPeriod;
use App\Models\Category;
use App\Models\FinancialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seedCurrencies();
});

function budgetToken(User $user): string
{
    return $user->createToken('budget-test', ['api'])->plainTextToken;
}

function budgetCategory(User $user, array $overrides = []): Category
{
    return Category::factory()->for($user)->create(array_merge([
        'kind' => 'expense', 'budget_enabled' => true, 'base_limit_minor_units' => 10000,
        'budget_currency_code' => 'ETB', 'rollover_enabled' => true, 'overspend_carry_enabled' => true, 'borrowing_enabled' => true,
    ], $overrides));
}

function budgetPeriodFor(User $user, Category $category, string $month): BudgetPeriod
{
    [$year, $monthNumber] = array_map('intval', explode('-', $month));

    return app(EnsureBudgetPeriodExists::class)->forCategory($user, $category, MonthlyPeriod::forMonth($year, $monthNumber, $user->budget_timezone));
}

test('budget reads lazily initialize a timezone-bound period and retries are idempotent', function (): void {
    $user = User::factory()->create(['base_currency_code' => 'ETB']);
    DB::table('users')->where('id', $user->id)->update(['budget_timezone' => 'Africa/Addis_Ababa']);
    $user = $user->fresh();
    $category = budgetCategory($user);
    $token = budgetToken($user);

    $this->withToken($token)->getJson('/api/v1/budgets?month=2026-08')->assertOk()
        ->assertJsonPath('data.budgets.0.category_id', $category->id)
        ->assertJsonPath('data.budgets.0.timezone', 'Africa/Addis_Ababa')
        ->assertJsonPath('data.budgets.0.period_start_at', '2026-07-31T21:00:00.000000Z');
    $this->withToken($token)->getJson('/api/v1/budgets?month=2026-08')->assertOk();

    $this->assertDatabaseCount('budget_periods', 1);
});

test('borrowing snapshots the full base limit and atomically reserves the immediate next period', function (): void {
    $user = User::factory()->create(['base_currency_code' => 'ETB', 'budget_timezone' => 'UTC']);
    $category = budgetCategory($user);
    $token = budgetToken($user);

    $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/v1/budgets/{$category->id}/borrow-next-month", ['month' => '2026-08'])
        ->assertCreated()->assertJsonPath('data.effective_limit_minor_units', 20000);
    $category->forceFill(['base_limit_minor_units' => 15000])->save();

    $september = budgetPeriodFor($user, $category, '2026-09');
    expect($september->base_limit_minor_units)->toBe(10000)
        ->and($september->borrowing_deduction_minor_units)->toBe(10000)
        ->and($september->effective_limit_minor_units)->toBe(10000)
        ->and(BudgetAdjustment::query()->where('type', 'borrowing_in')->value('base_limit_snapshot_minor_units'))->toBe(10000)
        ->and(BudgetAdjustment::query()->where('type', 'borrowing_reserved')->value('base_limit_snapshot_minor_units'))->toBe(10000);
    $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/v1/budgets/{$category->id}/borrow-next-month", ['month' => '2026-08'])
        ->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_STATE_TRANSITION');
    $thisexpect(BudgetAdjustment::query()->whereIn('type', ['borrowing_in', 'borrowing_reserved'])->count())->toBe(2);
});

test('reallocation is paired and budget actual spending only reads posted ledger transactions', function (): void {
    $user = User::factory()->create(['base_currency_code' => 'ETB', 'budget_timezone' => 'UTC']);
    $source = budgetCategory($user);
    $target = budgetCategory($user);
    $token = budgetToken($user);

    $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/v1/budgets/{$source->id}/reallocate", ['month' => '2026-08', 'target_category_id' => $target->id, 'amount_minor_units' => 2000])
        ->assertCreated()->assertJsonPath('data.effective_limit_minor_units', 8000);
    $account = FinancialAccount::factory()->for($user)->create(['currency_code' => 'ETB']);
    $draft = $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/transactions', [
        'financial_account_id' => $account->id, 'category_id' => $source->id, 'type' => 'expense',
        'occurred_at' => '2026-08-15T10:00:00Z', 'occurred_timezone' => 'UTC', 'original_amount_minor_units' => 1000,
        'original_currency_code' => 'ETB',
    ])->json('data');
    expect(budgetPeriodFor($user, $source, '2026-08')->actual_spent_minor_units)->toBe(0);
    $this->withToken($token)->withHeader('If-Match', '1')->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/v1/transactions/{$draft['id']}/post")->assertOk();

    expect(budgetPeriodFor($user, $source, '2026-08')->actual_spent_minor_units)->toBe(1000)
        ->and(BudgetAdjustment::query()->where('type', 'reallocation_out')->value('amount_minor_units'))->toBe(-2000)
        ->and(BudgetAdjustment::query()->where('type', 'reallocation_in')->value('amount_minor_units'))->toBe(2000);
});

test('a late posted expense creates immutable compensation instead of rewriting an established rollover', function (): void {
    $user = User::factory()->create(['base_currency_code' => 'ETB', 'budget_timezone' => 'UTC']);
    $category = budgetCategory($user, ['base_limit_minor_units' => 10000]);
    budgetPeriodFor($user, $category, '2026-08');
    $september = budgetPeriodFor($user, $category, '2026-09');
    $account = FinancialAccount::factory()->for($user)->create(['currency_code' => 'ETB']);
    $token = budgetToken($user);
    $draft = $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/transactions', [
        'financial_account_id' => $account->id, 'category_id' => $category->id, 'type' => 'expense',
        'occurred_at' => '2026-08-20T10:00:00Z', 'occurred_timezone' => 'UTC', 'original_amount_minor_units' => 9500, 'original_currency_code' => 'ETB',
    ])->json('data');
    $this->withToken($token)->withHeader('If-Match', '1')->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/v1/transactions/{$draft['id']}/post")->assertOk();

    expect(BudgetAdjustment::query()->where('budget_period_id', $september->id)->where('type', 'rollover')->value('amount_minor_units'))->toBe(10000)
        ->and(BudgetAdjustment::query()->where('budget_period_id', $september->id)->where('type', 'correction')->value('amount_minor_units'))->toBe(-9500);
});

test('a later refund corrects the original budget period and preserves the rollover history', function (): void {
    $user = User::factory()->create(['base_currency_code' => 'ETB', 'budget_timezone' => 'UTC']);
    $category = budgetCategory($user, ['base_limit_minor_units' => 10000]);
    budgetPeriodFor($user, $category, '2026-08');
    $september = budgetPeriodFor($user, $category, '2026-09');
    $account = FinancialAccount::factory()->for($user)->create(['currency_code' => 'ETB']);
    $token = budgetToken($user);
    $expense = $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/transactions', [
        'financial_account_id' => $account->id, 'category_id' => $category->id, 'type' => 'expense',
        'occurred_at' => '2026-08-15T10:00:00Z', 'occurred_timezone' => 'UTC', 'original_amount_minor_units' => 8000, 'original_currency_code' => 'ETB',
    ])->json('data');
    $expense = $this->withToken($token)->withHeader('If-Match', '1')->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/v1/transactions/{$expense['id']}/post")->json('data');
    $refund = $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/transactions', [
        'financial_account_id' => $account->id, 'category_id' => $category->id, 'type' => 'refund', 'related_transaction_id' => $expense['id'],
        'occurred_at' => '2026-09-05T10:00:00Z', 'occurred_timezone' => 'UTC', 'original_amount_minor_units' => 500, 'original_currency_code' => 'ETB',
    ])->json('data');
    $this->withToken($token)->withHeader('If-Match', '1')->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/v1/transactions/{$refund['id']}/post")->assertOk();

    expect(BudgetAdjustment::query()->where('budget_period_id', $september->id)->where('type', 'rollover')->value('amount_minor_units'))->toBe(10000)
        ->and((int) BudgetAdjustment::query()->where('budget_period_id', $september->id)->where('type', 'correction')->sum('amount_minor_units'))->toBe(-7500);
});

test('budget endpoints conceal another users category', function (): void {
    $owner = User::factory()->create(['base_currency_code' => 'ETB']);
    $category = budgetCategory($owner);
    $other = User::factory()->create(['base_currency_code' => 'ETB']);

    $this->withToken(budgetToken($other))->getJson("/api/v1/budgets/{$category->id}/periods")
        ->assertNotFound()->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
});
