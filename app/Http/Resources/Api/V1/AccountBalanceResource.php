<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountBalanceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['account_id' => $this['account_id'], 'currency_code' => $this['currency_code'], 'balance_minor_units' => (int) $this['balance_minor_units']];
    }
}
