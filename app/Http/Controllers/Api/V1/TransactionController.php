<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Transactions\PostTransactionService;
use App\Application\Transactions\TransactionService;
use App\Domain\Shared\Exceptions\DomainErrorCode;
use App\Domain\Shared\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CorrectFinancialTransactionRequest;
use App\Http\Requests\Api\V1\ReverseFinancialTransactionRequest;
use App\Http\Requests\Api\V1\StoreFinancialTransactionRequest;
use App\Http\Requests\Api\V1\UpdateFinancialTransactionRequest;
use App\Http\Resources\Api\V1\AccountBalanceResource;
use App\Http\Resources\Api\V1\FinancialTransactionResource;
use App\Models\FinancialTransaction;
use App\Models\JournalLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $transactions = FinancialTransaction::query()->ownedBy($request->user())
            ->when($request->string('state')->isNotEmpty(), fn ($query) => $query->where('state', $request->string('state')->toString()))
            ->when($request->string('type')->isNotEmpty(), fn ($query) => $query->where('type', $request->string('type')->toString()))
            ->orderByDesc('occurred_at')->orderByDesc('id')->cursorPaginate(min($request->integer('per_page', 25), 100));

        return $this->success($request, ['transactions' => FinancialTransactionResource::collection($transactions->items())->resolve($request)], 200, ['next_cursor' => $transactions->nextCursor()?->encode()]);
    }

    public function store(StoreFinancialTransactionRequest $request, TransactionService $transactions): JsonResponse
    {
        $transaction = $transactions->create($request->user(), $request->validated());

        return $this->success($request, (new FinancialTransactionResource($transaction))->resolve($request), JsonResponse::HTTP_CREATED);
    }

    public function show(Request $request, FinancialTransaction $transaction): JsonResponse
    {
        if ($request->user()->cannot('view', $transaction)) {
            return $this->notFound($request);
        }

        return $this->success($request, (new FinancialTransactionResource($transaction->load(['splits.category', 'journalEntry.lines'])))->resolve($request));
    }

    public function update(UpdateFinancialTransactionRequest $request, FinancialTransaction $transaction, TransactionService $transactions): JsonResponse
    {
        if ($request->user()->cannot('update', $transaction)) {
            return $this->notFound($request);
        }
        $updated = $transactions->update($request->user(), $transaction, $this->expectedVersion($request), $request->validated());

        return $this->success($request, (new FinancialTransactionResource($updated))->resolve($request));
    }

    public function destroy(Request $request, FinancialTransaction $transaction, TransactionService $transactions): JsonResponse
    {
        if ($request->user()->cannot('delete', $transaction)) {
            return $this->notFound($request);
        }
        $transactions->delete($request->user(), $transaction, $this->expectedVersion($request));

        return $this->success($request, ['id' => $transaction->getKey(), 'deleted' => true]);
    }

    public function post(Request $request, FinancialTransaction $transaction, PostTransactionService $transactions): JsonResponse
    {
        if ($request->user()->cannot('post', $transaction)) {
            return $this->notFound($request);
        }
        $posted = $transactions->post($request->user(), $transaction, $this->expectedVersion($request), $request->attributes->get('request_id'));

        return $this->success($request, (new FinancialTransactionResource($posted))->resolve($request));
    }

    public function reverse(ReverseFinancialTransactionRequest $request, FinancialTransaction $transaction, TransactionService $transactions): JsonResponse
    {
        if ($request->user()->cannot('reverse', $transaction)) {
            return $this->notFound($request);
        }
        $reversal = $transactions->reverse($request->user(), $transaction, $this->expectedVersion($request), (string) $request->validated('reason'));

        return $this->success($request, (new FinancialTransactionResource($reversal))->resolve($request), JsonResponse::HTTP_CREATED);
    }

    public function correct(CorrectFinancialTransactionRequest $request, FinancialTransaction $transaction, TransactionService $transactions): JsonResponse
    {
        if ($request->user()->cannot('correct', $transaction)) {
            return $this->notFound($request);
        }
        $attributes = $request->validated();
        $corrected = $transactions->correct($request->user(), $transaction, $this->expectedVersion($request), (string) $attributes['reason'], $attributes);

        return $this->success($request, (new FinancialTransactionResource($corrected))->resolve($request), JsonResponse::HTTP_CREATED);
    }

    public function balances(Request $request): JsonResponse
    {
        $balances = JournalLine::query()->selectRaw('financial_account_id AS account_id, currency_code, SUM(debit_minor_units - credit_minor_units) AS balance_minor_units')
            ->where('user_id', $request->user()->getKey())->whereNotNull('financial_account_id')->groupBy('financial_account_id', 'currency_code')->orderBy('financial_account_id')->get();

        return $this->success($request, ['balances' => AccountBalanceResource::collection($balances)->resolve($request)]);
    }

    private function expectedVersion(Request $request): int
    {
        $version = $request->header('If-Match');
        if (! is_string($version) || ! ctype_digit($version)) {
            throw DomainException::for(DomainErrorCode::ConcurrencyConflict, 'A current If-Match version is required.');
        }

        return (int) $version;
    }

    private function notFound(Request $request): JsonResponse
    {
        return $this->error($request, 'RESOURCE_NOT_FOUND', 'The requested resource was not found.', JsonResponse::HTTP_NOT_FOUND);
    }
}
