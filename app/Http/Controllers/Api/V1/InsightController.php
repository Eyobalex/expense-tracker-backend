<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Insights\CategoryAwareProjectedSpendCalculator;
use App\Application\Insights\IncomeConcentrationCalculator;
use App\Application\Insights\InsightTelemetry;
use App\Application\Insights\ItemPriceMovementCalculator;
use App\Application\Insights\SafeToSpendCalculator;
use App\Domain\Shared\Exceptions\DomainErrorCode;
use App\Domain\Shared\Time\MonthlyPeriod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\InsightPeriodRequest;
use App\Http\Resources\Api\V1\InsightResource;
use App\Models\LineItem;
use Illuminate\Http\JsonResponse;

class InsightController extends Controller
{
    public function forecast(InsightPeriodRequest $request, CategoryAwareProjectedSpendCalculator $forecast, InsightTelemetry $telemetry): JsonResponse
    {
        $startedAt = hrtime(true);

        return $this->respond($request, $forecast->calculate($request->user(), $this->period($request, $request->validated('month'))), $telemetry, $startedAt);
    }

    public function safeToSpend(InsightPeriodRequest $request, SafeToSpendCalculator $safeToSpend, InsightTelemetry $telemetry): JsonResponse
    {
        $startedAt = hrtime(true);

        return $this->respond($request, $safeToSpend->calculate($request->user(), $this->period($request, $request->validated('month'))), $telemetry, $startedAt);
    }

    public function incomeConcentration(InsightPeriodRequest $request, IncomeConcentrationCalculator $concentration, InsightTelemetry $telemetry): JsonResponse
    {
        $startedAt = hrtime(true);

        return $this->respond($request, $concentration->calculate($request->user()), $telemetry, $startedAt);
    }

    public function itemPrices(InsightPeriodRequest $request, ItemPriceMovementCalculator $prices, InsightTelemetry $telemetry): JsonResponse
    {
        $lineItemId = $request->validated('line_item_id');
        if (! is_string($lineItemId)) {
            return $this->error($request, DomainErrorCode::InvalidStateTransition->value, 'A receipt line item is required to calculate item price movement.', JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
        $lineItem = LineItem::query()->where('user_id', $request->user()->getKey())->find($lineItemId);
        if (! $lineItem instanceof LineItem) {
            return $this->error($request, DomainErrorCode::ResourceNotFound->value, 'The requested resource was not found.', JsonResponse::HTTP_NOT_FOUND);
        }

        $startedAt = hrtime(true);

        return $this->respond($request, $prices->calculate($request->user(), $lineItem), $telemetry, $startedAt);
    }

    /** @param array<string, mixed> $insight */
    private function respond(InsightPeriodRequest $request, array $insight, InsightTelemetry $telemetry, int $startedAtNanoseconds): JsonResponse
    {
        $telemetry->record($request, $request->user(), $insight, $startedAtNanoseconds);

        return $this->success($request, (new InsightResource($insight))->resolve($request));
    }

    private function period(InsightPeriodRequest $request, ?string $month): MonthlyPeriod
    {
        $selected = $month ?? now($request->user()->budget_timezone)->format('Y-m');
        [$year, $monthNumber] = array_map('intval', explode('-', $selected));

        return MonthlyPeriod::forMonth($year, $monthNumber, $request->user()->budget_timezone);
    }
}
