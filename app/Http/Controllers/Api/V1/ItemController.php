<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Catalog\CatalogService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MergeCatalogEntityRequest;
use App\Http\Requests\Api\V1\StoreItemRequest;
use App\Http\Resources\Api\V1\ItemResource;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = Item::query()->ownedBy($request->user())->whereNull('merged_into_id')->when($request->string('q')->isNotEmpty(), fn ($query) => $query->where('normalized_search_key', 'like', '%'.$request->string('q')->toString().'%'))->orderBy('canonical_name')->get();

        return $this->success($request, ['items' => ItemResource::collection($items)->resolve($request)]);
    }

    public function store(StoreItemRequest $request, CatalogService $catalog): JsonResponse
    {
        $item = $catalog->createItem($request->user(), $request->validated());

        return $this->success($request, (new ItemResource($item))->resolve($request), JsonResponse::HTTP_CREATED);
    }

    public function merge(MergeCatalogEntityRequest $request, Item $item, CatalogService $catalog): JsonResponse
    {
        if ($request->user()->cannot('update', $item)) {
            return $this->notFound($request);
        }
        $target = Item::query()->ownedBy($request->user())->find($request->validated('target_id'));
        if (! $target instanceof Item) {
            return $this->notFound($request);
        }
        $merged = $catalog->mergeItem($request->user(), $item, $target);

        return $this->success($request, (new ItemResource($merged))->resolve($request));
    }

    private function notFound(Request $request): JsonResponse
    {
        return $this->error($request, 'RESOURCE_NOT_FOUND', 'The requested resource was not found.', JsonResponse::HTTP_NOT_FOUND);
    }
}
