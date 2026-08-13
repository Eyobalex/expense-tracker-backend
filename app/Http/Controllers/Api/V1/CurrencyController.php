<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CurrencyResource;
use App\Models\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $currencies = Currency::query()->active()->orderBy('code')->get();

        return $this->success($request, ['currencies' => CurrencyResource::collection($currencies)->resolve($request)]);
    }
}
