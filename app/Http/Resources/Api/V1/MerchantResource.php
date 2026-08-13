<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Merchant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Merchant */
class MerchantResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Merchant $merchant */
        $merchant = $this->resource;

        return ['id' => $merchant->id, 'display_name' => $merchant->display_name, 'normalized_search_key' => $merchant->normalized_search_key, 'location' => $merchant->location, 'merged_into_id' => $merchant->merged_into_id, 'is_active' => $merchant->is_active, 'normalization_version' => $merchant->normalization_version, 'version' => $merchant->version, 'created_at' => $merchant->created_at?->toISOString(), 'updated_at' => $merchant->updated_at?->toISOString()];
    }
}
