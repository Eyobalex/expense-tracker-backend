<?php

use App\Domain\Sync\SyncCursor;
use App\Models\Category;
use App\Models\Device;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\SyncTombstone;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seedCurrencies();
});

function syncActor(): array
{
    $user = User::factory()->create(['base_currency_code' => 'ETB']);
    $device = Device::factory()->for($user)->create();

    return [$user, $device, $user->createToken('sync-test', ['api'])->plainTextToken];
}

/** @return array<string, mixed> */
function accountSyncPayload(Device $device, string $operationId): array
{
    return [
        'operation_id' => $operationId, 'device_id' => $device->client_device_id, 'entity' => 'account', 'action' => 'create',
        'local_id' => (string) Str::uuid(), 'payload' => ['name' => 'Offline cash', 'type' => 'cash', 'currency_code' => 'ETB'],
    ];
}

test('sync creates an account once and replays the recorded operation', function (): void {
    [$user, $device, $token] = syncActor();
    $payload = accountSyncPayload($device, (string) Str::uuid());

    $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/sync/push', $payload)
        ->assertAccepted()->assertJsonPath('data.status', 'succeeded')->assertJsonPath('data.entity', 'account');
    $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/sync/push', $payload)
        ->assertAccepted()->assertJsonPath('data.status', 'succeeded');

    expect(FinancialAccount::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and($user->syncOperations()->count())->toBe(1);
});

test('sync records stale mutations as conflicts without last-write-wins', function (): void {
    [$user, $device, $token] = syncActor();
    $account = FinancialAccount::factory()->for($user)->create(['currency_code' => 'ETB', 'version' => 2]);

    $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/sync/push', [
        'operation_id' => (string) Str::uuid(), 'device_id' => $device->client_device_id, 'entity' => 'account', 'action' => 'update',
        'server_id' => $account->id, 'expected_version' => 1, 'payload' => ['name' => 'Stale rename'],
    ])->assertAccepted()->assertJsonPath('data.status', 'conflict')->assertJsonPath('data.error.code', 'CONCURRENCY_CONFLICT');

    expect($account->fresh()->name)->not->toBe('Stale rename');
});

test('dependent transactions are rejected until their server dependencies exist', function (): void {
    [, $device, $token] = syncActor();

    $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/sync/push', [
        'operation_id' => (string) Str::uuid(), 'device_id' => $device->client_device_id, 'entity' => 'transaction', 'action' => 'create',
        'local_id' => (string) Str::uuid(), 'payload' => [
            'financial_account_id' => (string) Str::uuid(), 'category_id' => (string) Str::uuid(), 'type' => 'expense',
            'occurred_at' => '2026-08-16T10:00:00Z', 'occurred_timezone' => 'UTC', 'original_amount_minor_units' => 500, 'original_currency_code' => 'ETB',
        ],
    ])->assertAccepted()->assertJsonPath('data.status', 'failed')->assertJsonPath('data.error.code', 'SYNC_DEPENDENCY_UNRESOLVED');
});

test('sync requires an active owned device and preserves user isolation', function (): void {
    [$user, $device, $token] = syncActor();
    $otherDevice = Device::factory()->create();

    $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/sync/push', accountSyncPayload($otherDevice, (string) Str::uuid()))
        ->assertUnprocessable()->assertJsonPath('error.code', 'SYNC_DEVICE_INVALID');

    $operation = $user->syncOperations()->create(['device_id' => $device->id, 'client_operation_id' => (string) Str::uuid(), 'entity' => 'account', 'action' => 'create', 'payload_hash' => hash('sha256', 'one'), 'status' => 'succeeded']);
    $other = User::factory()->create();
    $this->actingAs($other, 'sanctum')->getJson("/api/v1/sync/operations/{$operation->id}")
        ->assertNotFound()->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
});

test('full sync is paginated with stable ordering and includes archived resources and pending server states', function (): void {
    [$user, $device, $token] = syncActor();
    $account = FinancialAccount::factory()->for($user)->create(['currency_code' => 'ETB', 'archived_at' => now()]);
    $category = Category::factory()->for($user)->create(['archived_at' => now(), 'is_active' => false]);
    FinancialTransaction::factory()->for($user)->create(['financial_account_id' => $account->id, 'state' => 'pending_review']);

    $first = $this->withToken($token)->getJson('/api/v1/sync/pull?full=1&limit=1')->assertOk()
        ->assertJsonPath('data.full_resync', true)->assertJsonPath('data.ordering.fields.0', 'changed_at');
    $next = $first->json('data.next_cursor');
    expect($next)->toBeString()->not->toBeEmpty();
    $second = $this->withToken($token)->getJson('/api/v1/sync/pull?full=1&limit=100&cursor='.urlencode($next))->assertOk();
    $changes = array_merge($first->json('data.changes'), $second->json('data.changes'));

    expect(collect($changes)->pluck('resource_type'))->toContain('account', 'category', 'transaction')
        ->and(collect($changes)->firstWhere('resource_type', 'transaction')['data']['state'])->toBe('pending_review');
});

test('expired cursors fail deterministically and transaction deletion emits a tombstone', function (): void {
    [$user, $device, $token] = syncActor();
    $expired = new SyncCursor(CarbonImmutable::now()->subDays(31), CarbonImmutable::now()->subDays(31));
    $this->withToken($token)->getJson('/api/v1/sync/pull?cursor='.urlencode($expired->encode()))
        ->assertConflict()->assertJsonPath('error.code', 'SYNC_CURSOR_EXPIRED');

    $account = FinancialAccount::factory()->for($user)->create(['currency_code' => 'ETB']);
    $transaction = FinancialTransaction::factory()->for($user)->create(['financial_account_id' => $account->id, 'state' => 'draft']);
    $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/sync/push', [
        'operation_id' => (string) Str::uuid(), 'device_id' => $device->client_device_id, 'entity' => 'transaction', 'action' => 'delete',
        'server_id' => $transaction->id, 'expected_version' => 1, 'payload' => [],
    ])->assertAccepted()->assertJsonPath('data.status', 'succeeded');

    expect(SyncTombstone::query()->where('user_id', $user->id)->where('resource_id', $transaction->id)->exists())->toBeTrue();
});
