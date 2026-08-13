<?php

use App\Application\Catalog\CatalogService;
use App\Domain\Catalog\CatalogTextNormalizer;
use App\Models\Item;
use App\Models\LineItem;
use App\Models\Merchant;
use App\Models\NormalizationCandidate;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function catalogHeaders(User $user): array
{
    return ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$user->createToken('catalog-test', ['api'])->plainTextToken, 'Idempotency-Key' => (string) Str::uuid()];
}

test('a user creates canonical merchants and items with normalized keys', function (): void {
    $user = User::factory()->create();

    $this->postJson('/api/v1/merchants', ['display_name' => 'Café—Market'], catalogHeaders($user))
        ->assertCreated()->assertJsonPath('data.normalized_search_key', 'café market');
    $this->postJson('/api/v1/items', ['canonical_name' => 'Coffee Beans', 'unit_code' => 'g', 'pack_size_value' => '500', 'pack_size_unit' => 'g'], catalogHeaders($user))
        ->assertCreated()->assertJsonPath('data.unit_code', 'g')->assertJsonPath('data.pack_size_unit', 'g');
});

test('catalog records are user scoped and merchant merges preserve source records with audit evidence', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $source = Merchant::factory()->for($user)->create(['display_name' => 'Old Shop', 'normalized_search_key' => 'old shop']);
    $target = Merchant::factory()->for($user)->create(['display_name' => 'New Shop', 'normalized_search_key' => 'new shop']);
    $otherMerchant = Merchant::factory()->for($other)->create();

    $this->getJson('/api/v1/merchants', ['Authorization' => 'Bearer '.$other->createToken('other', ['api'])->plainTextToken])->assertOk()->assertJsonCount(1, 'data.merchants');
    $this->postJson("/api/v1/merchants/{$source->id}/merge", ['target_id' => $target->id], catalogHeaders($user))->assertOk()->assertJsonPath('data.merged_into_id', $target->id);
    $source->refresh();
    expect($source->is_active)->toBeFalse()->and($source->merged_into_id)->toBe($target->id);
    $this->assertDatabaseHas('audit_events', ['event_name' => 'catalog.merchant_merged', 'aggregate_id' => $source->id]);
    $this->postJson("/api/v1/merchants/{$source->id}/merge", ['target_id' => $otherMerchant->id], catalogHeaders($user))->assertNotFound();
});

test('normalization candidates are idempotent and retain the immutable raw OCR merchant text', function (): void {
    $user = User::factory()->create();
    $merchant = Merchant::factory()->for($user)->create(['display_name' => 'Café Market', 'normalized_search_key' => 'café market']);
    $receipt = Receipt::factory()->for($user)->create();
    $catalog = app(CatalogService::class);

    $catalog->normalizeReceiptMerchant($receipt, 'Café—Market');
    $catalog->normalizeReceiptMerchant($receipt, 'Café—Market');

    $candidate = NormalizationCandidate::query()->sole();
    expect($candidate->candidate_merchant_id)->toBe($merchant->id)->and($candidate->raw_value)->toBe('Café—Market')->and($candidate->normalization_version)->toBe(CatalogTextNormalizer::VERSION);
});

test('a user explicitly resolves a normalization candidate without mutating raw evidence', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $merchant = Merchant::factory()->for($user)->create(['display_name' => 'Café Market', 'normalized_search_key' => 'café market']);
    $receipt = Receipt::factory()->for($user)->create();
    app(CatalogService::class)->normalizeReceiptMerchant($receipt, 'Café—Market');
    $candidate = NormalizationCandidate::query()->sole();

    $this->postJson("/api/v1/normalization-candidates/{$candidate->id}/resolve", ['decision' => 'accepted'], catalogHeaders($user))
        ->assertOk()->assertJsonPath('data.status', 'accepted')->assertJsonPath('data.raw_value', 'Café—Market');
    $this->postJson("/api/v1/normalization-candidates/{$candidate->id}/resolve", ['decision' => 'rejected'], catalogHeaders($other))
        ->assertNotFound()->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    $this->assertDatabaseHas('audit_events', ['event_name' => 'catalog.normalization_candidate_resolved', 'aggregate_id' => $candidate->id]);
    expect($receipt->fresh()->original_filename)->toBe('receipt.jpg')
        ->and($merchant->fresh()->display_name)->toBe('Café Market');
});

test('item candidates exclude incompatible units and only link the canonical item after explicit acceptance', function (): void {
    $this->seedCurrencies();
    $user = User::factory()->create();
    $item = Item::factory()->for($user)->create(['canonical_name' => 'Coffee Beans', 'normalized_search_key' => 'coffee beans', 'unit_code' => 'g', 'pack_size_unit' => 'g']);
    Item::factory()->for($user)->create(['canonical_name' => 'Coffee Beans Liquid', 'normalized_search_key' => 'coffee beans liquid', 'unit_code' => 'ml', 'pack_size_unit' => 'ml']);
    $lineItem = LineItem::factory()->for($user)->create(['raw_description' => 'Coffee—Beans', 'unit_code' => 'g', 'pack_size_unit' => 'g']);

    app(CatalogService::class)->normalizeLineItem($lineItem);

    $candidate = NormalizationCandidate::query()->sole();
    expect($candidate->candidate_item_id)->toBe($item->id)
        ->and($candidate->raw_value)->toBe('Coffee—Beans')
        ->and($lineItem->fresh()->canonical_item_id)->toBeNull();

    $this->postJson("/api/v1/normalization-candidates/{$candidate->id}/resolve", ['decision' => 'accepted'], catalogHeaders($user))
        ->assertOk()->assertJsonPath('data.status', 'accepted');

    expect($lineItem->fresh()->canonical_item_id)->toBe($item->id)
        ->and($lineItem->fresh()->raw_description)->toBe('Coffee—Beans');
});
