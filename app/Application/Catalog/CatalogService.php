<?php

namespace App\Application\Catalog;

use App\Domain\Catalog\CatalogTextNormalizer;
use App\Domain\Shared\Exceptions\DomainErrorCode;
use App\Domain\Shared\Exceptions\DomainException;
use App\Models\AuditEvent;
use App\Models\Item;
use App\Models\LineItem;
use App\Models\Merchant;
use App\Models\NormalizationCandidate;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CatalogService
{
    public function __construct(private CatalogTextNormalizer $normalizer) {}

    /** @param array<string, mixed> $attributes */
    public function createMerchant(User $user, array $attributes): Merchant
    {
        $displayName = (string) $attributes['display_name'];

        return DB::transaction(function () use ($user, $attributes, $displayName): Merchant {
            $key = $this->requiredKey($displayName);
            if ($user->merchants()->where('normalized_search_key', $key)->exists()) {
                throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'A merchant with this normalized name already exists.');
            }
            $merchant = new Merchant(['display_name' => $displayName, 'normalized_search_key' => $key, 'location' => $attributes['location'] ?? null, 'normalization_version' => CatalogTextNormalizer::VERSION]);
            $merchant->user()->associate($user);
            $merchant->save();

            return $merchant;
        }, attempts: 3);
    }

    /** @param array<string, mixed> $attributes */
    public function createItem(User $user, array $attributes): Item
    {
        $name = (string) $attributes['canonical_name'];

        return DB::transaction(function () use ($user, $attributes, $name): Item {
            $key = $this->requiredKey($name);
            if ($user->items()->where('normalized_search_key', $key)->exists()) {
                throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'An item with this normalized name already exists.');
            }
            $item = new Item(['canonical_name' => $name, 'normalized_search_key' => $key, 'unit_code' => $attributes['unit_code'] ?? null, 'pack_size_value' => $attributes['pack_size_value'] ?? null, 'pack_size_unit' => $attributes['pack_size_unit'] ?? null, 'normalization_version' => CatalogTextNormalizer::VERSION]);
            $item->user()->associate($user);
            $item->save();

            return $item;
        }, attempts: 3);
    }

    public function mergeMerchant(User $user, Merchant $source, Merchant $target): Merchant
    {
        $this->assertMerge($user, $source->user_id, $source->id, $source->merged_into_id, $target->user_id, $target->id, $target->merged_into_id, $target->is_active, 'merchant');

        return DB::transaction(function () use ($user, $source, $target): Merchant {
            $source->forceFill(['merged_into_id' => $target->id, 'is_active' => false, 'version' => $source->version + 1])->save();
            $this->auditMerge($user, 'merchant', $source->id, $target->id);

            return $source->refresh();
        }, attempts: 3);
    }

    public function mergeItem(User $user, Item $source, Item $target): Item
    {
        $this->assertMerge($user, $source->user_id, $source->id, $source->merged_into_id, $target->user_id, $target->id, $target->merged_into_id, $target->is_active, 'item');
        if (! $this->normalizer->compatibleUnits($source->unit_code, $target->unit_code, $source->pack_size_unit, $target->pack_size_unit)) {
            throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'Items with incompatible unit dimensions cannot be merged.');
        }

        return DB::transaction(function () use ($user, $source, $target): Item {
            $source->forceFill(['merged_into_id' => $target->id, 'is_active' => false, 'version' => $source->version + 1])->save();
            $this->auditMerge($user, 'item', $source->id, $target->id);

            return $source->refresh();
        }, attempts: 3);
    }

    public function normalizeReceiptMerchant(Receipt $receipt, string $rawValue): void
    {
        $key = $this->normalizer->normalize($rawValue);
        if ($key === '') {
            return;
        }
        $merchants = Merchant::query()->where('user_id', $receipt->user_id)->where('is_active', true)->whereNull('merged_into_id')->get();
        foreach ($merchants as $merchant) {
            $score = $this->normalizer->similarity($key, $merchant->normalized_search_key);
            if ($score < 0.65) {
                continue;
            }
            NormalizationCandidate::query()->firstOrCreate(['receipt_id' => $receipt->id, 'entity_type' => 'merchant', 'normalized_search_key' => $key, 'candidate_merchant_id' => $merchant->id], ['user_id' => $receipt->user_id, 'raw_value' => $rawValue, 'score' => number_format($score, 6, '.', ''), 'normalization_version' => CatalogTextNormalizer::VERSION, 'status' => 'suggested']);
        }
    }

    public function normalizeLineItem(LineItem $lineItem): void
    {
        $key = $this->normalizer->normalize($lineItem->raw_description);
        if ($key === '') {
            return;
        }
        $items = Item::query()->where('user_id', $lineItem->user_id)->where('is_active', true)->whereNull('merged_into_id')->get();
        foreach ($items as $item) {
            if (! $this->normalizer->compatibleUnits($lineItem->unit_code, $item->unit_code, $lineItem->pack_size_unit, $item->pack_size_unit)) {
                continue;
            }
            $score = $this->normalizer->similarity($key, $item->normalized_search_key);
            if ($score < 0.65) {
                continue;
            }
            NormalizationCandidate::query()->firstOrCreate(
                ['line_item_id' => $lineItem->id, 'entity_type' => 'item', 'normalized_search_key' => $key, 'candidate_item_id' => $item->id],
                ['user_id' => $lineItem->user_id, 'raw_value' => $lineItem->raw_description, 'score' => number_format($score, 6, '.', ''), 'normalization_version' => CatalogTextNormalizer::VERSION, 'status' => 'suggested'],
            );
        }
    }

    private function requiredKey(string $value): string
    {
        $key = $this->normalizer->normalize($value);
        if ($key === '') {
            throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'The canonical name must contain letters or numbers.');
        }

        return $key;
    }

    public function resolveCandidate(User $user, NormalizationCandidate $candidate, string $decision): NormalizationCandidate
    {
        if ($candidate->user_id !== $user->id || $candidate->status !== 'suggested' || ! in_array($decision, ['accepted', 'rejected'], true)) {
            throw DomainException::for(DomainErrorCode::ResourceNotFound, 'The normalization candidate was not found.');
        }

        return DB::transaction(function () use ($user, $candidate, $decision): NormalizationCandidate {
            $candidate->forceFill(['status' => $decision, 'resolved_at' => now()])->save();
            if ($decision === 'accepted') {
                NormalizationCandidate::query()->where('user_id', $user->id)->where('receipt_id', $candidate->receipt_id)->where('entity_type', $candidate->entity_type)->where('id', '<>', $candidate->id)->where('status', 'suggested')->update(['status' => 'superseded', 'resolved_at' => now()]);
                if ($candidate->entity_type === 'item' && $candidate->lineItem !== null) {
                    $candidate->lineItem->forceFill(['canonical_item_id' => $candidate->candidate_item_id, 'normalization_version' => $candidate->normalization_version])->save();
                    NormalizationCandidate::query()->where('user_id', $user->id)->where('line_item_id', $candidate->line_item_id)->where('entity_type', 'item')->where('id', '<>', $candidate->id)->where('status', 'suggested')->update(['status' => 'superseded', 'resolved_at' => now()]);
                }
            }
            AuditEvent::query()->create(['actor_user_id' => $user->id, 'user_id' => $user->id, 'event_name' => 'catalog.normalization_candidate_resolved', 'aggregate_type' => 'normalization_candidate', 'aggregate_id' => $candidate->id, 'summary' => ['decision' => $decision, 'receipt_id' => $candidate->receipt_id, 'entity_type' => $candidate->entity_type]]);

            return $candidate->refresh();
        }, attempts: 3);
    }

    private function assertMerge(User $user, int $sourceUserId, string $sourceId, ?string $sourceMergedIntoId, int $targetUserId, string $targetId, ?string $targetMergedIntoId, bool $targetActive, string $entity): void
    {
        if ($sourceUserId !== $user->id || $targetUserId !== $user->id || $sourceId === $targetId || $sourceMergedIntoId !== null || $targetMergedIntoId !== null || ! $targetActive) {
            throw DomainException::for(DomainErrorCode::ResourceNotFound, "The {$entity} merge target was not found.");
        }
    }

    private function auditMerge(User $user, string $entity, string $sourceId, string $targetId): void
    {
        AuditEvent::query()->create(['actor_user_id' => $user->id, 'user_id' => $user->id, 'event_name' => "catalog.{$entity}_merged", 'aggregate_type' => $entity, 'aggregate_id' => $sourceId, 'summary' => ["source_{$entity}_id" => $sourceId, "target_{$entity}_id" => $targetId]]);
    }
}
