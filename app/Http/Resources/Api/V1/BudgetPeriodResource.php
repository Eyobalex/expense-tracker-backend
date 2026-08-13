<?php

namespace App\Http\Resources\Api\V1;

use App\Models\BudgetPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BudgetPeriod */
class BudgetPeriodResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var BudgetPeriod $period */
        $period = $this->resource;

        return [
            'id' => $period->id,
            'category_id' => $period->category_id,
            'year' => $period->period_year,
            'month' => $period->period_month,
            'timezone' => $period->budget_timezone,
            'period_start_at' => $period->period_start_at->toISOString(),
            'period_end_at' => $period->period_end_at->toISOString(),
            'status' => $period->status,
            'currency_code' => $period->currency_code,
            'base_limit_minor_units' => $period->base_limit_minor_units,
            'borrowing_deduction_minor_units' => $period->borrowing_deduction_minor_units,
            'positive_rollover_minor_units' => $period->positive_rollover_minor_units,
            'negative_carry_minor_units' => $period->negative_carry_minor_units,
            'reallocation_in_minor_units' => $period->reallocation_in_minor_units,
            'reallocation_out_minor_units' => $period->reallocation_out_minor_units,
            'effective_limit_minor_units' => $period->effective_limit_minor_units,
            'actual_spent_minor_units' => $period->actual_spent_minor_units,
            'remaining_minor_units' => $period->remaining_minor_units,
            'version' => $period->version,
        ];
    }
}
