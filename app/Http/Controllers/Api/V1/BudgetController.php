<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Budgeting\BudgetService;
use App\Domain\Shared\Exceptions\DomainErrorCode;
use App\Domain\Shared\Time\MonthlyPeriod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BorrowNextMonthRequest;
use App\Http\Requests\Api\V1\ReallocateBudgetRequest;
use App\Http\Resources\Api\V1\BudgetPeriodResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    public function index(Request $request, BudgetService $budgets): JsonResponse
    {
        $period = $this->period($request, (string) $request->query('month', now($request->user()->budget_timezone)->format('Y-m')));
        $periods = $budgets->periodsFor($request->user(), $period);

        return $this->success($request, ['budgets' => BudgetPeriodResource::collection($periods)->resolve($request)]);
    }

    public function history(Request $request, Category $category): JsonResponse
    {
        if ($request->user()->cannot('view', $category)) {
            return $this->notFound($request);
        }
        $periods = $category->budgetPeriods()->ownedBy($request->user())->orderByDesc('period_year')->orderByDesc('period_month')->get();

        return $this->success($request, ['budgets' => BudgetPeriodResource::collection($periods)->resolve($request)]);
    }

    public function borrow(BorrowNextMonthRequest $request, Category $category, BudgetService $budgets): JsonResponse
    {
        if ($request->user()->cannot('update', $category)) {
            return $this->notFound($request);
        }
        $period = $this->period($request, (string) $request->validated('month'));
        $budget = $budgets->borrowNextMonth($request->user(), $category, $period);

        return $this->success($request, (new BudgetPeriodResource($budget))->resolve($request), JsonResponse::HTTP_CREATED);
    }

    public function reallocate(ReallocateBudgetRequest $request, Category $category, BudgetService $budgets): JsonResponse
    {
        if ($request->user()->cannot('update', $category)) {
            return $this->notFound($request);
        }
        /** @var Category|null $target */
        $target = $request->user()->categories()->whereKey($request->validated('target_category_id'))->first();
        if (! $target instanceof Category) {
            return $this->notFound($request);
        }
        $period = $this->period($request, (string) $request->validated('month'));
        $budget = $budgets->reallocate($request->user(), $category, $target, $period, (int) $request->validated('amount_minor_units'));

        return $this->success($request, (new BudgetPeriodResource($budget))->resolve($request), JsonResponse::HTTP_CREATED);
    }

    private function period(Request $request, string $month): MonthlyPeriod
    {
        [$year, $monthNumber] = array_map('intval', explode('-', $month));

        return MonthlyPeriod::forMonth($year, $monthNumber, $request->user()->budget_timezone);
    }

    private function notFound(Request $request): JsonResponse
    {
        return $this->error($request, DomainErrorCode::ResourceNotFound->value, 'The requested resource was not found.', JsonResponse::HTTP_NOT_FOUND);
    }
}
