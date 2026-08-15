<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Insights\DashboardService;
use App\Domain\Shared\Time\MonthlyPeriod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\InsightPeriodRequest;
use App\Http\Resources\Api\V1\DashboardResource;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function show(InsightPeriodRequest $request, DashboardService $dashboard): JsonResponse
    {
        $period = $this->period($request, $request->validated('month'));

        return $this->success($request, (new DashboardResource($dashboard->summary($request->user(), $period)))->resolve($request));
    }

    private function period(InsightPeriodRequest $request, ?string $month): MonthlyPeriod
    {
        $selected = $month ?? now($request->user()->budget_timezone)->format('Y-m');
        [$year, $monthNumber] = array_map('intval', explode('-', $selected));

        return MonthlyPeriod::forMonth($year, $monthNumber, $request->user()->budget_timezone);
    }
}
