<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Accounts\FinancialAccountService;
use App\Domain\Shared\Exceptions\DomainErrorCode;
use App\Domain\Shared\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreFinancialAccountRequest;
use App\Http\Requests\Api\V1\UpdateFinancialAccountRequest;
use App\Http\Resources\Api\V1\FinancialAccountResource;
use App\Models\FinancialAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialAccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $accounts = FinancialAccount::query()->ownedBy($request->user())->orderBy('name')->get();

        return $this->success($request, ['accounts' => FinancialAccountResource::collection($accounts)->resolve($request)]);
    }

    public function store(StoreFinancialAccountRequest $request, FinancialAccountService $accounts): JsonResponse
    {
        /** @var array{name: string, type: string, currency_code: string, opening_balance_configured?: bool} $attributes */
        $attributes = $request->validated();
        $account = $accounts->create($request->user(), $attributes);

        return $this->success($request, (new FinancialAccountResource($account))->resolve($request), JsonResponse::HTTP_CREATED);
    }

    public function show(Request $request, FinancialAccount $financialAccount): JsonResponse
    {
        if ($request->user()->cannot('view', $financialAccount)) {
            return $this->notFound($request);
        }

        return $this->success($request, (new FinancialAccountResource($financialAccount))->resolve($request));
    }

    public function update(UpdateFinancialAccountRequest $request, FinancialAccount $financialAccount, FinancialAccountService $accounts): JsonResponse
    {
        if ($request->user()->cannot('update', $financialAccount)) {
            return $this->notFound($request);
        }
        $account = $accounts->update($request->user(), $financialAccount, $this->expectedVersion($request), $request->validated());

        return $this->success($request, (new FinancialAccountResource($account))->resolve($request));
    }

    public function archive(Request $request, FinancialAccount $financialAccount, FinancialAccountService $accounts): JsonResponse
    {
        if ($request->user()->cannot('archive', $financialAccount)) {
            return $this->notFound($request);
        }
        $account = $accounts->archive($request->user(), $financialAccount, $this->expectedVersion($request));

        return $this->success($request, (new FinancialAccountResource($account))->resolve($request));
    }

    public function restore(Request $request, FinancialAccount $financialAccount, FinancialAccountService $accounts): JsonResponse
    {
        if ($request->user()->cannot('restore', $financialAccount)) {
            return $this->notFound($request);
        }
        $account = $accounts->restore($request->user(), $financialAccount, $this->expectedVersion($request));

        return $this->success($request, (new FinancialAccountResource($account))->resolve($request));
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
