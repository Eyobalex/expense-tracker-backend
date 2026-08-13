<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Catalog\CatalogService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ResolveNormalizationCandidateRequest;
use App\Http\Resources\Api\V1\NormalizationCandidateResource;
use App\Models\NormalizationCandidate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NormalizationCandidateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $candidates = NormalizationCandidate::query()->where('user_id', $request->user()->getKey())->where('status', 'suggested')->orderByDesc('score')->get();

        return $this->success($request, ['normalization_candidates' => NormalizationCandidateResource::collection($candidates)->resolve($request)]);
    }

    public function resolve(ResolveNormalizationCandidateRequest $request, NormalizationCandidate $candidate, CatalogService $catalog): JsonResponse
    {
        if ($request->user()->cannot('resolve', $candidate)) {
            return $this->notFound($request);
        }
        $resolved = $catalog->resolveCandidate($request->user(), $candidate, (string) $request->validated('decision'));

        return $this->success($request, (new NormalizationCandidateResource($resolved))->resolve($request));
    }

    private function notFound(Request $request): JsonResponse
    {
        return $this->error($request, 'RESOURCE_NOT_FOUND', 'The requested resource was not found.', JsonResponse::HTTP_NOT_FOUND);
    }
}
