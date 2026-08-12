<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Category */
class CategoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Category $category */
        $category = $this->resource;

        return [
            'id' => $category->id,
            'parent_id' => $category->parent_id,
            'name' => $category->name,
            'kind' => $category->kind,
            'is_active' => $category->is_active,
            'is_system' => $category->is_system,
            'budget_enabled' => $category->budget_enabled,
            'base_limit_minor_units' => $category->base_limit_minor_units,
            'budget_currency_code' => $category->budget_currency_code,
            'rollover_enabled' => $category->rollover_enabled,
            'overspend_carry_enabled' => $category->overspend_carry_enabled,
            'borrowing_enabled' => $category->borrowing_enabled,
            'archived_at' => $category->archived_at?->toISOString(),
            'version' => $category->version,
            'created_at' => $category->created_at?->toISOString(),
            'updated_at' => $category->updated_at?->toISOString(),
        ];
    }
}
