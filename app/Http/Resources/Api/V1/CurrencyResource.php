<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Currency */
class CurrencyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Currency $currency */
        $currency = $this->resource;

        return [
            'code' => $currency->code,
            'display_name' => $currency->display_name,
            'symbol' => $currency->symbol,
            'minor_unit_exponent' => $currency->minor_unit_exponent,
        ];
    }
}
