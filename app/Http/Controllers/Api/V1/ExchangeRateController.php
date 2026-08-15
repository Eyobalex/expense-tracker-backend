<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ExchangeRateResource;
use App\Models\ExchangeRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExchangeRateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rates = ExchangeRate::query()
            ->orderByDesc('rate_date')
            ->orderBy('base_currency_code')
            ->orderBy('quote_currency_code')
            ->cursorPaginate(min($request->integer('per_page', 25), 100));

        return $this->success($request, ['exchange_rates' => ExchangeRateResource::collection($rates->items())->resolve($request)], 200, [
            'next_cursor' => $rates->nextCursor()?->encode(),
        ]);
    }
}
