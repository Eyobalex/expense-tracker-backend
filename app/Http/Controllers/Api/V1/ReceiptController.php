<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Receipts\ReceiptProcessingService;
use App\Application\Receipts\ReceiptReviewService;
use App\Application\Receipts\ReceiptStorageService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RetryReceiptOcrRequest;
use App\Http\Requests\Api\V1\StoreReceiptRequest;
use App\Http\Requests\Api\V1\UpdateReceiptReviewRequest;
use App\Http\Resources\Api\V1\FinancialTransactionResource;
use App\Http\Resources\Api\V1\ReceiptResource;
use App\Models\Receipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceiptController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $receipts = Receipt::query()->ownedBy($request->user())->with('extractions')->whereNull('deleted_at')->latest('created_at')->cursorPaginate(min($request->integer('per_page', 25), 100));

        return $this->success($request, ['receipts' => ReceiptResource::collection($receipts->items())->resolve($request)], 200, ['next_cursor' => $receipts->nextCursor()?->encode()]);
    }

    public function store(StoreReceiptRequest $request, ReceiptStorageService $storage, ReceiptProcessingService $processing): JsonResponse
    {
        $receipt = $storage->store($request->user(), $request->file('receipt'), (string) $request->attributes->get('request_id'));
        if ($receipt->status === 'uploaded') {
            $processing->queue($receipt);
        }

        return $this->success($request, (new ReceiptResource($receipt))->resolve($request), JsonResponse::HTTP_CREATED);
    }

    public function show(Request $request, Receipt $receipt): JsonResponse
    {
        if ($request->user()->cannot('view', $receipt)) {
            return $this->notFound($request);
        }

        return $this->success($request, (new ReceiptResource($receipt->load('extractions')))->resolve($request));
    }

    public function download(Request $request, Receipt $receipt): StreamedResponse|JsonResponse
    {
        if ($request->user()->cannot('view', $receipt)) {
            return $this->notFound($request);
        }

        return Storage::disk((string) config('receipts.disk'))->download($receipt->original_object_key, $receipt->original_filename ?: 'receipt.'.$receipt->extension, ['Content-Type' => $receipt->mime_type]);
    }

    public function retry(RetryReceiptOcrRequest $request, Receipt $receipt, ReceiptProcessingService $processing): JsonResponse
    {
        if ($request->user()->cannot('retry', $receipt)) {
            return $this->notFound($request);
        }
        $receipt->forceFill(['status' => 'uploaded', 'failed_at' => null, 'failure_reason' => null])->save();
        $processing->queue($receipt);

        return $this->success($request, (new ReceiptResource($receipt))->resolve($request), JsonResponse::HTTP_ACCEPTED);
    }

    public function createReviewTransaction(UpdateReceiptReviewRequest $request, Receipt $receipt, ReceiptReviewService $review): JsonResponse
    {
        if ($request->user()->cannot('update', $receipt)) {
            return $this->notFound($request);
        }
        $transaction = $review->createTransaction($request->user(), $receipt, $request->validated());

        return $this->success($request, (new FinancialTransactionResource($transaction))->resolve($request), JsonResponse::HTTP_CREATED);
    }

    private function notFound(Request $request): JsonResponse
    {
        return $this->error($request, 'RESOURCE_NOT_FOUND', 'The requested resource was not found.', JsonResponse::HTTP_NOT_FOUND);
    }
}
