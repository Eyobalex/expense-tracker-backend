<?php

namespace App\Application\Sync;

use App\Application\Accounts\FinancialAccountService;
use App\Application\Catalog\CatalogService;
use App\Application\Categories\CategoryService;
use App\Application\Transactions\TransactionService;
use App\Domain\Shared\Exceptions\DomainErrorCode;
use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Sync\SyncChange;
use App\Domain\Sync\SyncCursor;
use App\Http\Requests\Api\V1\StoreCategoryRequest;
use App\Http\Requests\Api\V1\StoreFinancialAccountRequest;
use App\Http\Requests\Api\V1\StoreFinancialTransactionRequest;
use App\Http\Requests\Api\V1\StoreItemRequest;
use App\Http\Requests\Api\V1\StoreMerchantRequest;
use App\Http\Requests\Api\V1\UpdateCategoryRequest;
use App\Http\Requests\Api\V1\UpdateFinancialAccountRequest;
use App\Http\Requests\Api\V1\UpdateFinancialTransactionRequest;
use App\Http\Resources\Api\V1\BudgetPeriodResource;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Http\Resources\Api\V1\FinancialAccountResource;
use App\Http\Resources\Api\V1\FinancialTransactionResource;
use App\Http\Resources\Api\V1\ItemResource;
use App\Http\Resources\Api\V1\MerchantResource;
use App\Http\Resources\Api\V1\ReceiptResource;
use App\Models\BudgetPeriod;
use App\Models\Category;
use App\Models\Device;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\Item;
use App\Models\Merchant;
use App\Models\Receipt;
use App\Models\SyncOperation;
use App\Models\SyncTombstone;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class SyncService
{
    public function __construct(
        private FinancialAccountService $accounts,
        private CategoryService $categories,
        private CatalogService $catalog,
        private TransactionService $transactions,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function push(User $user, array $attributes): SyncOperation
    {
        $device = $user->devices()->where('client_device_id', $attributes['device_id'])->whereNull('revoked_at')->first();
        if (! $device instanceof Device) {
            throw DomainException::for(DomainErrorCode::SyncDeviceInvalid, 'The synchronization device is not active for this user.');
        }

        $hash = hash('sha256', $this->canonicalJson($attributes));

        return DB::transaction(function () use ($user, $attributes, $device, $hash): SyncOperation {
            $existing = SyncOperation::query()->ownedBy($user)->where('device_id', $device->getKey())->where('client_operation_id', $attributes['operation_id'])->lockForUpdate()->first();
            if ($existing instanceof SyncOperation) {
                if (! hash_equals($existing->payload_hash, $hash)) {
                    throw DomainException::for(DomainErrorCode::InvalidOperationId, 'A synchronization operation ID cannot be reused with a different request.');
                }

                return $existing;
            }

            $operation = $user->syncOperations()->create([
                'device_id' => $device->getKey(), 'client_operation_id' => $attributes['operation_id'], 'entity' => $attributes['entity'],
                'action' => $attributes['action'], 'local_id' => $attributes['local_id'] ?? null, 'server_id' => $attributes['server_id'] ?? null,
                'expected_version' => $attributes['expected_version'] ?? null, 'payload_hash' => $hash, 'status' => 'processing',
                'client_occurred_at' => isset($attributes['client_occurred_at']) ? CarbonImmutable::parse((string) $attributes['client_occurred_at']) : null,
            ]);

            try {
                $resource = $this->apply($user, $operation, $attributes);
                $data = $this->resourceData($resource);
                $operation->forceFill(['server_id' => $resource->getKey(), 'status' => 'succeeded', 'response_payload' => $data, 'completed_at' => now()])->save();
            } catch (ValidationException $exception) {
                $operation->forceFill(['status' => 'failed', 'error_code' => 'VALIDATION_FAILED', 'error_fields' => $exception->errors(), 'completed_at' => now()])->save();
            } catch (DomainException $exception) {
                $operation->forceFill(['status' => $exception->errorCode() === DomainErrorCode::ConcurrencyConflict ? 'conflict' : 'failed', 'error_code' => $exception->errorCode()->value, 'completed_at' => now()])->save();
            }

            return $operation->refresh();
        }, attempts: 3);
    }

    /** @return array{changes: list<array<string, mixed>>, next_cursor: string|null, authoritative_cursor: string, full_resync: bool, ordering: array<string, mixed>} */
    public function pull(User $user, ?string $cursorValue, bool $fullResync, ?int $requestedPageSize): array
    {
        $cursor = $cursorValue === null ? null : SyncCursor::decode($cursorValue);
        if ($cursor instanceof SyncCursor && ! $cursor->fullResync && $cursor->sinceAt->lt(now()->subDays((int) config('sync.cursor_retention_days')))) {
            throw DomainException::for(DomainErrorCode::SyncCursorExpired, 'The synchronization cursor is invalid or has expired.');
        }
        if ($cursor instanceof SyncCursor && $cursor->fullResync !== $fullResync) {
            throw DomainException::for(DomainErrorCode::SyncCursorExpired, 'The synchronization cursor cannot be used for a different synchronization mode.');
        }

        $pageSize = min(max($requestedPageSize ?? (int) config('sync.page_size'), 1), (int) config('sync.maximum_page_size'));
        $snapshotAt = $cursor?->lastChangedAt === null ? CarbonImmutable::now() : $cursor->snapshotAt;
        $sinceAt = $cursor instanceof SyncCursor ? $cursor->sinceAt : ($fullResync ? CarbonImmutable::createFromTimestampUTC(0) : CarbonImmutable::now());
        $effectiveCursor = $cursor ?? new SyncCursor($sinceAt, $snapshotAt, fullResync: $fullResync);
        $changes = $this->changes($user, $effectiveCursor, $pageSize + 1);
        $hasMore = count($changes) > $pageSize;
        $page = array_slice($changes, 0, $pageSize);
        $last = $page === [] ? null : $page[array_key_last($page)];
        $nextCursor = $hasMore && $last instanceof SyncChange ? new SyncCursor($effectiveCursor->sinceAt, $effectiveCursor->snapshotAt, $last->changedAt, $last->resourceType, $last->resourceId, $fullResync) : null;
        $authoritativeCursor = new SyncCursor($effectiveCursor->snapshotAt, $effectiveCursor->snapshotAt);

        return [
            'changes' => array_map(fn (SyncChange $change): array => $change->toArray(), $page),
            'next_cursor' => $nextCursor?->encode(), 'authoritative_cursor' => $authoritativeCursor->encode(), 'full_resync' => $fullResync,
            'ordering' => ['fields' => ['changed_at', 'resource_type', 'resource_id'], 'direction' => 'ascending', 'snapshot_at' => $effectiveCursor->snapshotAt->toISOString(), 'tombstone_retention_days' => (int) config('sync.tombstone_retention_days')],
        ];
    }

    /** @param array<string, mixed> $attributes */
    private function apply(User $user, SyncOperation $operation, array $attributes): Model
    {
        $entity = (string) $attributes['entity'];
        $action = (string) $attributes['action'];
        /** @var array<string, mixed> $payload */
        $payload = $attributes['payload'];
        $serverId = $attributes['server_id'] ?? null;
        $expectedVersion = $attributes['expected_version'] ?? null;

        return match ($entity) {
            'account' => $this->applyAccount($user, $action, $serverId, $expectedVersion, $payload),
            'category' => $this->applyCategory($user, $action, $serverId, $expectedVersion, $payload),
            'merchant' => $this->applyMerchant($user, $action, $payload),
            'item' => $this->applyItem($user, $action, $payload),
            'transaction' => $this->applyTransaction($user, $action, $serverId, $expectedVersion, $payload),
            default => throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'The synchronization entity is not supported.'),
        };
    }

    /** @param array<string, mixed> $payload */
    private function applyAccount(User $user, string $action, ?string $serverId, ?int $expectedVersion, array $payload): FinancialAccount
    {
        if ($action === 'create') {
            /** @var array{name: string, type: string, currency_code: string, opening_balance_configured?: bool} $attributes */
            $attributes = $this->validated($payload, new StoreFinancialAccountRequest);

            return $this->accounts->create($user, $attributes);
        }
        $account = $this->account($user, $serverId);

        return match ($action) {
            'update' => $this->accounts->update($user, $account, $this->requiredVersion($expectedVersion), $this->validated($payload, new UpdateFinancialAccountRequest)),
            'archive' => $this->accounts->archive($user, $account, $this->requiredVersion($expectedVersion)),
            'restore' => $this->accounts->restore($user, $account, $this->requiredVersion($expectedVersion)),
            default => throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'The synchronization account action is not supported.'),
        };
    }

    /** @param array<string, mixed> $payload */
    private function applyCategory(User $user, string $action, ?string $serverId, ?int $expectedVersion, array $payload): Category
    {
        if ($action === 'create') {
            /** @var array{name: string, kind: string, parent_id?: string|null, budget_enabled?: bool, base_limit_minor_units?: int|null, rollover_enabled?: bool, overspend_carry_enabled?: bool, borrowing_enabled?: bool, budget_currency_code?: string|null} $attributes */
            $attributes = $this->validated($payload, new StoreCategoryRequest);

            return $this->categories->create($user, $attributes);
        }
        $category = $this->category($user, $serverId);

        return match ($action) {
            'update' => $this->categories->update($user, $category, $this->requiredVersion($expectedVersion), $this->validated($payload, new UpdateCategoryRequest)),
            'archive' => $this->categories->archive($user, $category, $this->requiredVersion($expectedVersion)),
            'restore' => $this->categories->restore($user, $category, $this->requiredVersion($expectedVersion)),
            default => throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'The synchronization category action is not supported.'),
        };
    }

    /** @param array<string, mixed> $payload */
    private function applyMerchant(User $user, string $action, array $payload): Merchant
    {
        if ($action !== 'create') {
            throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'Only merchant creation is supported by synchronization.');
        }

        return $this->catalog->createMerchant($user, $this->validated($payload, new StoreMerchantRequest));
    }

    /** @param array<string, mixed> $payload */
    private function applyItem(User $user, string $action, array $payload): Item
    {
        if ($action !== 'create') {
            throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'Only item creation is supported by synchronization.');
        }

        return $this->catalog->createItem($user, $this->validated($payload, new StoreItemRequest));
    }

    /** @param array<string, mixed> $payload */
    private function applyTransaction(User $user, string $action, ?string $serverId, ?int $expectedVersion, array $payload): FinancialTransaction
    {
        if ($action === 'create') {
            $this->assertTransactionDependencies($user, $payload);

            return $this->transactions->create($user, $this->validated($payload, new StoreFinancialTransactionRequest));
        }
        $transaction = $this->transaction($user, $serverId);
        if ($action === 'update') {
            $this->assertTransactionDependencies($user, $payload);

            return $this->transactions->update($user, $transaction, $this->requiredVersion($expectedVersion), $this->validated($payload, new UpdateFinancialTransactionRequest));
        }
        if ($action === 'post') {
            return $this->transactions->post($user, $transaction, $this->requiredVersion($expectedVersion));
        }
        if ($action === 'delete') {
            $this->transactions->delete($user, $transaction, $this->requiredVersion($expectedVersion));

            return $transaction;
        }

        throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'The synchronization transaction action is not supported.');
    }

    /** @return list<SyncChange> */
    private function changes(User $user, SyncCursor $cursor, int $limit): array
    {
        $queries = [];
        foreach ($this->resourceMap() as $type => $definition) {
            /** @var class-string<Model> $model */
            $model = $definition['model'];
            $query = $model::query()->selectRaw('id as resource_id, ? as resource_type, version, updated_at as changed_at', [$type])->where('user_id', $user->id)->where('updated_at', '<=', $cursor->snapshotAt);
            if (! $cursor->fullResync) {
                $query->where('updated_at', '>', $cursor->sinceAt);
            }
            $queries[] = $query;
        }
        $tombstones = SyncTombstone::query()->selectRaw('resource_id, resource_type, version, deleted_at as changed_at')->where('user_id', $user->id)->where('deleted_at', '<=', $cursor->snapshotAt)->where('deleted_at', '>=', now()->subDays((int) config('sync.tombstone_retention_days')));
        if (! $cursor->fullResync) {
            $tombstones->where('deleted_at', '>', $cursor->sinceAt);
        }
        $queries[] = $tombstones;

        $union = array_shift($queries);
        foreach ($queries as $query) {
            $union->unionAll($query);
        }
        $feed = DB::query()->fromSub($union, 'sync_changes')->orderBy('changed_at')->orderBy('resource_type')->orderBy('resource_id');
        if ($cursor->lastChangedAt !== null && $cursor->lastResourceType !== null && $cursor->lastResourceId !== null) {
            $feed->where(function ($query) use ($cursor): void {
                $query->where('changed_at', '>', $cursor->lastChangedAt)
                    ->orWhere(function ($query) use ($cursor): void {
                        $query->where('changed_at', $cursor->lastChangedAt)->where('resource_type', '>', $cursor->lastResourceType)
                            ->orWhere(function ($query) use ($cursor): void {
                                $query->where('changed_at', $cursor->lastChangedAt)->where('resource_type', $cursor->lastResourceType)->where('resource_id', '>', $cursor->lastResourceId);
                            });
                    });
            });
        }

        /** @var list<SyncChange> $changes */
        $changes = $feed->limit($limit)->get()->map(function (object $row) use ($user): SyncChange {
            $changedAt = CarbonImmutable::parse($row->changed_at);
            if ($row->resource_type === 'transaction' && ! FinancialTransaction::query()->ownedBy($user)->whereKey($row->resource_id)->exists()) {
                return new SyncChange('transaction', $row->resource_id, 'delete', (int) $row->version, $changedAt, null);
            }
            if (array_key_exists($row->resource_type, $this->resourceMap())) {
                $definition = $this->resourceMap()[$row->resource_type];
                /** @var class-string<Model> $model */
                $model = $definition['model'];
                $resource = $model::query()->where('user_id', $user->id)->find($row->resource_id);
                if ($resource instanceof Model) {
                    return new SyncChange($row->resource_type, $row->resource_id, 'upsert', (int) $row->version, $changedAt, $this->resourceData($resource));
                }
            }

            return new SyncChange((string) $row->resource_type, (string) $row->resource_id, 'delete', (int) $row->version, $changedAt, null);
        })->values()->all();

        return $changes;
    }

    /** @return array<string, array{model: class-string<Model>, resource: class-string}> */
    private function resourceMap(): array
    {
        return [
            'account' => ['model' => FinancialAccount::class, 'resource' => FinancialAccountResource::class],
            'category' => ['model' => Category::class, 'resource' => CategoryResource::class],
            'budget_period' => ['model' => BudgetPeriod::class, 'resource' => BudgetPeriodResource::class],
            'merchant' => ['model' => Merchant::class, 'resource' => MerchantResource::class],
            'item' => ['model' => Item::class, 'resource' => ItemResource::class],
            'receipt' => ['model' => Receipt::class, 'resource' => ReceiptResource::class],
            'transaction' => ['model' => FinancialTransaction::class, 'resource' => FinancialTransactionResource::class],
        ];
    }

    /** @param class-string<Model> $model */
    private function owned(User $user, string $model, ?string $id, string $label): Model
    {
        $resource = $id === null ? null : $model::query()->where('user_id', $user->id)->find($id);
        if (! $resource instanceof Model) {
            throw DomainException::for(DomainErrorCode::SyncDependencyUnresolved, "The synchronized {$label} is not available for this user.");
        }

        return $resource;
    }

    private function requiredVersion(?int $version): int
    {
        if ($version === null) {
            throw DomainException::for(DomainErrorCode::ConcurrencyConflict, 'A synchronized update requires an expected version.');
        }

        return $version;
    }

    private function account(User $user, ?string $id): FinancialAccount
    {
        $resource = $this->owned($user, FinancialAccount::class, $id, 'financial account');
        assert($resource instanceof FinancialAccount);

        return $resource;
    }

    private function category(User $user, ?string $id): Category
    {
        $resource = $this->owned($user, Category::class, $id, 'category');
        assert($resource instanceof Category);

        return $resource;
    }

    private function transaction(User $user, ?string $id): FinancialTransaction
    {
        $resource = $this->owned($user, FinancialTransaction::class, $id, 'transaction');
        assert($resource instanceof FinancialTransaction);

        return $resource;
    }

    /** @param array<string, mixed> $payload */
    private function assertTransactionDependencies(User $user, array $payload): void
    {
        $relationships = [
            'financial_account_id' => $user->financialAccounts(), 'counterparty_account_id' => $user->financialAccounts(),
            'category_id' => $user->categories(), 'merchant_id' => $user->merchants(), 'related_transaction_id' => $user->financialTransactions(),
        ];
        foreach ($relationships as $key => $relationship) {
            if (isset($payload[$key]) && ! $relationship->whereKey($payload[$key])->exists()) {
                throw DomainException::for(DomainErrorCode::SyncDependencyUnresolved, 'Synchronization dependencies must be accepted before dependent transactions.');
            }
        }
        foreach ($payload['splits'] ?? [] as $split) {
            if (! isset($split['category_id']) || ! $user->categories()->whereKey($split['category_id'])->exists()) {
                throw DomainException::for(DomainErrorCode::SyncDependencyUnresolved, 'Synchronization dependencies must be accepted before dependent transactions.');
            }
            if (isset($split['canonical_item_id']) && ! $user->items()->whereKey($split['canonical_item_id'])->exists()) {
                throw DomainException::for(DomainErrorCode::SyncDependencyUnresolved, 'Synchronization dependencies must be accepted before dependent transactions.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validated(array $payload, StoreCategoryRequest|StoreFinancialAccountRequest|StoreFinancialTransactionRequest|StoreItemRequest|StoreMerchantRequest|UpdateCategoryRequest|UpdateFinancialAccountRequest|UpdateFinancialTransactionRequest $request): array
    {
        return Validator::make($payload, $request->rules())->validate();
    }

    /** @return array<string, mixed> */
    private function resourceData(Model $resource): array
    {
        return match (true) {
            $resource instanceof FinancialAccount => (new FinancialAccountResource($resource))->resolve(request()),
            $resource instanceof Category => (new CategoryResource($resource))->resolve(request()),
            $resource instanceof BudgetPeriod => (new BudgetPeriodResource($resource))->resolve(request()),
            $resource instanceof Merchant => (new MerchantResource($resource))->resolve(request()),
            $resource instanceof Item => (new ItemResource($resource))->resolve(request()),
            $resource instanceof Receipt => (new ReceiptResource($resource))->resolve(request()),
            $resource instanceof FinancialTransaction => (new FinancialTransactionResource($resource))->resolve(request()),
            default => [],
        };
    }

    /** @param array<string, mixed> $attributes */
    private function canonicalJson(array $attributes): string
    {
        $normalised = $this->sortRecursively($attributes);

        return json_encode($normalised, JSON_THROW_ON_ERROR);
    }

    private function sortRecursively(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        foreach ($value as $key => $item) {
            $value[$key] = $this->sortRecursively($item);
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
