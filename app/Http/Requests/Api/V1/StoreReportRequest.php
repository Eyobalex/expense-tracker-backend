<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Reporting\Enums\ReportFormat;
use App\Domain\Reporting\Enums\ReportType;
use App\Models\Category;
use App\Models\Currency;
use App\Models\FinancialAccount;
use App\Models\Merchant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(array_column(ReportType::cases(), 'value'))],
            'format' => ['required', 'string', Rule::in(array_column(ReportFormat::cases(), 'value'))],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'financial_account_id' => ['nullable', 'uuid'],
            'category_id' => ['nullable', 'uuid'],
            'merchant_id' => ['nullable', 'uuid'],
            'transaction_type' => ['nullable', 'string', Rule::in(['expense', 'income', 'transfer', 'credit_card_purchase', 'credit_card_repayment', 'refund', 'fee', 'opening_balance', 'adjustment'])],
            'currency_code' => ['nullable', 'string', 'size:3'],
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $type = ReportType::from((string) $this->validated('type'));
            $format = ReportFormat::from((string) $this->validated('format'));
            if (($type === ReportType::FullJsonDataExport && $format !== ReportFormat::Json) || ($type === ReportType::FullAccountExport && $format !== ReportFormat::Zip) || (! $type->isPortabilityExport() && $format === ReportFormat::Zip)) {
                $validator->errors()->add('format', 'The selected format is not available for this export type.');
            }

            $userId = $this->user()?->getKey();
            foreach ([
                'financial_account_id' => FinancialAccount::class,
                'category_id' => Category::class,
                'merchant_id' => Merchant::class,
            ] as $field => $model) {
                $id = $this->validated($field);
                if (is_string($id) && ! $model::query()->whereKey($id)->where('user_id', $userId)->exists()) {
                    $validator->errors()->add($field, 'The selected resource is invalid.');
                }
            }

            $currencyCode = $this->validated('currency_code');
            if (is_string($currencyCode) && ! Currency::query()->whereKey($currencyCode)->exists()) {
                $validator->errors()->add('currency_code', 'The selected currency is invalid.');
            }
        }];
    }
}
