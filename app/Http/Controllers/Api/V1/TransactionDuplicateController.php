<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Transactions\DuplicateDetectionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ResolveTransactionDuplicateRequest;
use App\Http\Resources\Api\V1\TransactionDuplicateCandidateResource;
use App\Models\FinancialTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionDuplicateController extends Controller
{
    public function index(Request $request, FinancialTransaction $transaction, DuplicateDetectionService $duplicates): JsonResponse
    {
        if ($request->user()->cannot('view', $transaction)) {
            return $this->notFound($request);
        }

        $candidates = $duplicates->discover($request->user(), $transaction);

        return $this->success($request, ['duplicates' => TransactionDuplicateCandidateResource::collection($candidates)->resolve($request)]);
    }

    public function resolve(ResolveTransactionDuplicateRequest $request, FinancialTransaction $transaction, DuplicateDetectionService $duplicates): JsonResponse
    {
        if ($request->user()->cannot('resolveDuplicate', $transaction)) {
            return $this->notFound($request);
        }

        $candidate = $duplicates->resolve($request->user(), $transaction, (string) $request->validated('candidate_id'), (string) $request->validated('decision'));

        return $this->success($request, (new TransactionDuplicateCandidateResource($candidate))->resolve($request));
    }

    private function notFound(Request $request): JsonResponse
    {
        return $this->error($request, 'RESOURCE_NOT_FOUND', 'The requested resource was not found.', JsonResponse::HTTP_NOT_FOUND);
    }
}
