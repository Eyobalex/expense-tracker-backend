<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExchangeRateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ExchangeRate $rate */
        $rate = $this->resource;

        return [
            'id' => $rate->id,
            'base_currency_code' => $rate->base_currency_code,
            'quote_currency_code' => $rate->quote_currency_code,
            'rate' => $rate->rate,
            'rate_date' => $rate->rate_date->toDateString(),
            'provider' => $rate->provider,
            'provider_published_at' => $rate->provider_published_at?->toISOString(),
            'retrieved_at' => $rate->retrieved_at->toISOString(),
            'status' => $rate->status,
        ];
    }
}
