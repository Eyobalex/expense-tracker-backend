<?php

namespace App\Http\Requests\Api\V1;

class CorrectFinancialTransactionRequest extends StoreFinancialTransactionRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['reason'] = ['required', 'string', 'max:500'];

        return $rules;
    }
}
