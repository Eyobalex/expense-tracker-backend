<?php

namespace App\Application\Reporting;

use App\Models\AuditEvent;
use App\Models\BudgetAdjustment;
use App\Models\BudgetPeriod;
use App\Models\Category;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\InsightSnapshot;
use App\Models\Item;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LineItem;
use App\Models\Merchant;
use App\Models\NormalizationCandidate;
use App\Models\Receipt;
use App\Models\ReceiptDerivative;
use App\Models\ReceiptOcrExtraction;
use App\Models\SyncOperation;
use App\Models\SyncTombstone;
use App\Models\TransactionDuplicateCandidate;
use App\Models\TransactionSplit;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

final class FullAccountExportBuilder
{
    /** @return array{data:array<string, mixed>, receipts:list<array{id:string,key:string,extension:string}>} */
    public function build(User $user, bool $includeReceiptMedia = false): array
    {
        $receipts = Receipt::query()->ownedBy($user)->whereNull('deleted_at')->orderBy('id')->get();

        return [
            'data' => [
                'export_type' => $includeReceiptMedia ? 'full_account_export' : 'full_json_data_export',
                'schema_version' => 1,
                'generated_at' => now()->toISOString(),
                'user' => Arr::only($user->toArray(), ['id', 'uuid', 'name', 'email', 'base_currency_code', 'timezone', 'budget_timezone', 'onboarding_completed', 'created_at', 'updated_at']),
                'entities' => [
                    'currencies' => Currency::query()->whereIn('code', $this->currencyCodes($user))->orderBy('code')->get()->map(fn (Currency $currency): array => $this->record($currency))->all(),
                    'financial_accounts' => $this->owned(FinancialAccount::class, $user),
                    'categories' => $this->owned(Category::class, $user),
                    'financial_transactions' => $this->owned(FinancialTransaction::class, $user),
                    'transaction_splits' => $this->related(TransactionSplit::class, 'financial_transaction_id', $this->ids(FinancialTransaction::query()->ownedBy($user)->get(['id']))),
                    'journal_entries' => $this->owned(JournalEntry::class, $user),
                    'journal_lines' => $this->owned(JournalLine::class, $user),
                    'budget_periods' => $this->owned(BudgetPeriod::class, $user),
                    'budget_adjustments' => $this->owned(BudgetAdjustment::class, $user),
                    'merchants' => $this->owned(Merchant::class, $user),
                    'items' => $this->owned(Item::class, $user),
                    'line_items' => $this->owned(LineItem::class, $user),
                    'normalization_candidates' => $this->owned(NormalizationCandidate::class, $user),
                    'duplicate_candidates' => $this->owned(TransactionDuplicateCandidate::class, $user),
                    'exchange_rates' => ExchangeRate::query()->whereIn('base_currency_code', $this->currencyCodes($user))->orWhereIn('quote_currency_code', $this->currencyCodes($user))->orderBy('rate_date')->get()->map(fn (ExchangeRate $rate): array => $this->record($rate))->all(),
                    'receipts' => $receipts->map(fn (Receipt $receipt): array => $includeReceiptMedia
                        ? [...$this->record($receipt), 'export_media_path' => 'receipts/'.$receipt->getKey().'.'.$receipt->extension]
                        : $this->record($receipt))->all(),
                    'receipt_derivatives_metadata' => $this->related(ReceiptDerivative::class, 'receipt_id', $this->ids($receipts)),
                    'receipt_ocr_extractions_metadata' => $this->related(ReceiptOcrExtraction::class, 'receipt_id', $this->ids($receipts), ['raw_response']),
                    'sync_operations' => $this->owned(SyncOperation::class, $user, ['response_payload']),
                    'sync_tombstones' => $this->owned(SyncTombstone::class, $user),
                    'insight_snapshots' => $this->owned(InsightSnapshot::class, $user),
                    'notifications' => $this->owned(UserNotification::class, $user),
                    'audit_events' => $this->owned(AuditEvent::class, $user),
                ],
            ],
            'receipts' => array_values($receipts->map(fn (Receipt $receipt): array => ['id' => (string) $receipt->getKey(), 'key' => $receipt->original_object_key, 'extension' => $receipt->extension])->all()),
        ];
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  list<string>  $except
     * @return list<array<string, mixed>>
     */
    private function owned(string $modelClass, User $user, array $except = []): array
    {
        return array_values($modelClass::query()->where('user_id', $user->getKey())->orderBy('id')->get()->map(fn (Model $model): array => $this->record($model, $except))->all());
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  list<string>  $ids
     * @param  list<string>  $except
     * @return list<array<string, mixed>>
     */
    private function related(string $modelClass, string $foreignKey, array $ids, array $except = []): array
    {
        $ids = collect($ids)->filter()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        return array_values($modelClass::query()->whereIn($foreignKey, $ids)->orderBy('id')->get()->map(fn (Model $model): array => $this->record($model, $except))->all());
    }

    /** @param list<string> $except
     * @return array<string, mixed>
     */
    private function record(Model $model, array $except = []): array
    {
        return Arr::except($model->toArray(), [...$except, 'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'push_token']);
    }

    /** @return list<string> */
    private function currencyCodes(User $user): array
    {
        return array_values(FinancialTransaction::query()->ownedBy($user)->whereNotNull('base_currency_code')->pluck('base_currency_code')->push($user->base_currency_code)->filter()->map(fn (mixed $code): string => (string) $code)->unique()->all());
    }

    /** @param iterable<Model> $models
     * @return list<string>
     */
    private function ids(iterable $models): array
    {
        $ids = [];
        foreach ($models as $model) {
            $ids[] = (string) $model->getKey();
        }

        return $ids;
    }
}
