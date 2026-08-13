<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Item */
class ItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Item $item */
        $item = $this->resource;

        return ['id' => $item->id, 'canonical_name' => $item->canonical_name, 'normalized_search_key' => $item->normalized_search_key, 'unit_code' => $item->unit_code, 'pack_size_value' => $item->pack_size_value, 'pack_size_unit' => $item->pack_size_unit, 'merged_into_id' => $item->merged_into_id, 'is_active' => $item->is_active, 'normalization_version' => $item->normalization_version, 'version' => $item->version, 'created_at' => $item->created_at?->toISOString(), 'updated_at' => $item->updated_at?->toISOString()];
    }
}
