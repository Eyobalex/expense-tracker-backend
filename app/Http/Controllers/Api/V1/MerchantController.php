<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Catalog\CatalogService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MergeCatalogEntityRequest;
use App\Http\Requests\Api\V1\StoreMerchantRequest;
use App\Http\Resources\Api\V1\MerchantResource;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $merchants = Merchant::query()->ownedBy($request->user())->whereNull('merged_into_id')->when($request->string('q')->isNotEmpty(), fn ($query) => $query->where('normalized_search_key', 'like', '%'.$request->string('q')->toString().'%'))->orderBy('display_name')->get();

        return $this->success($request, ['merchants' => MerchantResource::collection($merchants)->resolve($request)]);
    }

    public function store(StoreMerchantRequest $request, CatalogService $catalog): JsonResponse
    {
        $merchant = $catalog->createMerchant($request->user(), $request->validated());

        return $this->success($request, (new MerchantResource($merchant))->resolve($request), JsonResponse::HTTP_CREATED);
    }

    public function merge(MergeCatalogEntityRequest $request, Merchant $merchant, CatalogService $catalog): JsonResponse
    {
        if ($request->user()->cannot('update', $merchant)) {
            return $this->notFound($request);
        }
        $target = Merchant::query()->ownedBy($request->user())->find($request->validated('target_id'));
        if (! $target instanceof Merchant) {
            return $this->notFound($request);
        }
        $merged = $catalog->mergeMerchant($request->user(), $merchant, $target);

        return $this->success($request, (new MerchantResource($merged))->resolve($request));
    }

    private function notFound(Request $request): JsonResponse
    {
        return $this->error($request, 'RESOURCE_NOT_FOUND', 'The requested resource was not found.', JsonResponse::HTTP_NOT_FOUND);
    }
}
