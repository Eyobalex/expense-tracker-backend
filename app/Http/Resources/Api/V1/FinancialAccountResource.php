<?php

namespace App\Http\Resources\Api\V1;

use App\Models\FinancialAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FinancialAccount */
class FinancialAccountResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var FinancialAccount $account */
        $account = $this->resource;

        return [
            'id' => $account->id,
            'name' => $account->name,
            'type' => $account->type,
            'accounting_type' => $account->accounting_type,
            'currency_code' => $account->currency_code,
            'opening_balance_configured' => $account->opening_balance_configured,
            'archived_at' => $account->archived_at?->toISOString(),
            'version' => $account->version,
            'created_at' => $account->created_at?->toISOString(),
            'updated_at' => $account->updated_at?->toISOString(),
        ];
    }
}
