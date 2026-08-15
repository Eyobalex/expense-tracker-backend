<?php

use App\Application\Currency\ExchangeRateRefreshService;
use App\Domain\Currency\Contracts\HistoricalExchangeRateLookup;
use App\Domain\Currency\Enums\RateLookupStatus;
use App\Domain\Currency\ValueObjects\CurrencyCode;
use App\Jobs\RefreshExchangeRates;
use App\Models\Category;
use App\Models\ExchangeRate;
use App\Models\FinancialAccount;
use App\Models\FxProviderSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seedCurrencies();
    config()->set('services.open_exchange_rates.app_id', 'test-app-id');
});

function fxToken(User $user): string
{
    return $user->createToken('fx-test', ['api'])->plainTextToken;
}

function fxTransactionPayload(FinancialAccount $account, Category $category, array $overrides = []): array
{
    return array_merge([
        'financial_account_id' => $account->id,
        'category_id' => $category->id,
        'type' => 'expense',
        'occurred_at' => '2026-08-14T10:00:00+03:00',
        'occurred_timezone' => 'Africa/Addis_Ababa',
        'original_amount_minor_units' => 10000,
        'original_currency_code' => 'USD',
        'description' => 'Foreign expense',
    ], $overrides);
}

test('the provider refresh stores the approved USD to ETB source pair and exposes it through the API', function (): void {
    Http::fake([
        'openexchangerates.org/api/historical/2026-08-14.json*' => Http::response([
            'timestamp' => 1786665600,
            'rates' => ['ETB' => '55.123456789012345678'],
        ]),
    ]);

    $rate = app(ExchangeRateRefreshService::class)->refresh(new DateTimeImmutable('2026-08-14'));
    expect($rate->base_currency_code)->toBe('USD')->and($rate->quote_currency_code)->toBe('ETB')->and($rate->rate)->toBe('55.123456789012345678');
    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'app_id=test-app-id') && str_contains($request->url(), 'symbols=ETB'));

    $user = User::factory()->create(['base_currency_code' => 'ETB']);
    $this->withToken(fxToken($user))->getJson('/api/v1/exchange-rates')->assertOk()
        ->assertJsonPath('data.exchange_rates.0.base_currency_code', 'USD')
        ->assertJsonPath('data.exchange_rates.0.quote_currency_code', 'ETB');
});

test('historical lookup returns an exact direct rate, an exact reciprocal, and a stale fallback deterministically', function (): void {
    ExchangeRate::factory()->create(['rate_date' => '2026-08-14', 'rate' => '55.000000000000000000', 'retrieved_at' => now()]);
    $lookup = app(HistoricalExchangeRateLookup::class);

    $direct = $lookup->find(CurrencyCode::fromString('USD'), CurrencyCode::fromString('ETB'), new DateTimeImmutable('2026-08-14'));
    $inverse = $lookup->find(CurrencyCode::fromString('ETB'), CurrencyCode::fromString('USD'), new DateTimeImmutable('2026-08-14'));
    expect($direct->status)->toBe(RateLookupStatus::Exact)
        ->and($direct->quote?->rate->decimal())->toBe('55.000000000000000000')
        ->and($inverse->status)->toBe(RateLookupStatus::Exact)
        ->and($inverse->quote?->rate->decimal())->toBe('0.018181818181818182');
    expect($lookup->find(CurrencyCode::fromString('USD'), CurrencyCode::fromString('ETB'), new DateTimeImmutable('2026-08-16'))->status)
        ->toBe(RateLookupStatus::LatestValid);

    ExchangeRate::query()->sole()->update(['retrieved_at' => now()->subHours(49)]);
    expect($lookup->find(CurrencyCode::fromString('USD'), CurrencyCode::fromString('ETB'), new DateTimeImmutable('2026-08-15'))->status)->toBe(RateLookupStatus::Stale);
});

test('the seeded operational setting controls the configured pair and rate freshness windows', function (): void {
    FxProviderSetting::factory()->create(['alert_after_hours' => 2, 'maximum_staleness_hours' => 4]);
    ExchangeRate::factory()->create(['rate_date' => '2026-08-14', 'retrieved_at' => now()->subHours(5)]);

    $lookup = app(HistoricalExchangeRateLookup::class);

    expect($lookup->find(CurrencyCode::fromString('USD'), CurrencyCode::fromString('ETB'), new DateTimeImmutable('2026-08-14'))->status)
        ->toBe(RateLookupStatus::Stale);
});

test('a rate older than the alert threshold emits an operational freshness alert before it becomes stale', function (): void {
    Log::spy();
    ExchangeRate::factory()->create(['rate_date' => '2026-08-14', 'retrieved_at' => now()->subHours(25)]);

    $result = app(HistoricalExchangeRateLookup::class)->find(
        CurrencyCode::fromString('USD'),
        CurrencyCode::fromString('ETB'),
        new DateTimeImmutable('2026-08-15'),
    );

    expect($result->status)->toBe(RateLookupStatus::LatestValid);
    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $event, array $context): bool => $event === 'fx.rate_freshness_alert' && $context['age_hours'] >= 24);
});

test('provider failures retry three times without persisting a rate', function (): void {
    Http::fake(['openexchangerates.org/api/historical/2026-08-14.json*' => Http::response([], 503)]);

    expect(fn () => app(ExchangeRateRefreshService::class)->refresh(new DateTimeImmutable('2026-08-14')))
        ->toThrow(RequestException::class);
    Http::assertSentCount(4);
    expect(ExchangeRate::query()->count())->toBe(0);
});

test('posting automatically locks a fresh provider rate and blocks stale automatic FX while allowing an audited override', function (): void {
    $user = User::factory()->create(['base_currency_code' => 'ETB']);
    $account = FinancialAccount::factory()->for($user)->create(['currency_code' => 'USD']);
    $category = Category::factory()->for($user)->create(['kind' => 'expense']);
    $token = fxToken($user);
    ExchangeRate::factory()->create(['rate_date' => '2026-08-14', 'rate' => '55.000000000000000000', 'retrieved_at' => now()]);

    $draft = $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/transactions', fxTransactionPayload($account, $category))->assertCreated()->json('data');
    $this->withToken($token)->withHeader('If-Match', '1')->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/v1/transactions/{$draft['id']}/post")->assertOk()
        ->assertJsonPath('data.used_rate', '55.000000000000000000')
        ->assertJsonPath('data.rate_source', 'open_exchange_rates');

    ExchangeRate::query()->update(['retrieved_at' => now()->subHours(49)]);
    $override = $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/transactions', fxTransactionPayload($account, $category, [
        'occurred_at' => '2026-08-15T10:00:00+03:00',
        'original_amount_minor_units' => 20000,
        'used_rate' => '56',
        'reference_rate' => '55',
        'rate_override_reason' => 'Documented cash-exchange receipt',
    ]))->assertCreated()->json('data');
    $this->withToken($token)->withHeader('If-Match', '1')->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/v1/transactions/{$override['id']}/post")->assertOk()
        ->assertJsonPath('data.used_rate', '56.000000000000000000')
        ->assertJsonPath('data.reference_rate', '55.000000000000000000')
        ->assertJsonPath('data.rate_source', 'manual_override');
    expect($user->auditEvents()->where('action', 'transaction.fx_rate_overridden')->count())->toBe(1);

    $staleDraft = $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/transactions', fxTransactionPayload($account, $category, [
        'occurred_at' => '2026-08-20T10:00:00+03:00',
        'original_amount_minor_units' => 30000,
    ]))->assertCreated()->json('data');
    $this->withToken($token)->withHeader('If-Match', '1')->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/v1/transactions/{$staleDraft['id']}/post")
        ->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_RATE_LOCK');
});

test('rate source and rounding mode are server-controlled', function (): void {
    $user = User::factory()->create(['base_currency_code' => 'ETB']);
    $account = FinancialAccount::factory()->for($user)->create(['currency_code' => 'USD']);
    $category = Category::factory()->for($user)->create(['kind' => 'expense']);

    $this->withToken(fxToken($user))->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/transactions', fxTransactionPayload($account, $category, [
            'rate_source' => 'forged_provider',
            'rounding_mode' => 'UP',
        ]))
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_FAILED')
        ->assertJsonStructure(['error' => ['fields' => ['rate_source', 'rounding_mode']]]);
});

test('the refresh command dispatches the retryable job for the selected UTC date', function (): void {
    Queue::fake();

    $this->artisan('fx:refresh-rates --date=2026-08-14')->assertSuccessful();

    Queue::assertPushed(RefreshExchangeRates::class, fn (RefreshExchangeRates $job): bool => $job->rateDate === '2026-08-14');
});
