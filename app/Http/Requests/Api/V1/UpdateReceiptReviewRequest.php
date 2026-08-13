<?php

namespace App\Http\Requests\Api\V1;

class UpdateReceiptReviewRequest extends StoreFinancialTransactionRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }
}
