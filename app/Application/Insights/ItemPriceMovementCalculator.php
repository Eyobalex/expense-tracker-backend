<?php

namespace App\Application\Insights;

use App\Domain\Insights\Enums\FormulaName;
use App\Domain\Insights\InsightFormula;
use App\Domain\Insights\UnitNormalizer;
use App\Domain\Shared\Time\Clock;
use App\Models\LineItem;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

/** @phpstan-type PriceLine array{id: string, canonical_item_id: string, currency_code: string, quantity: string, unit_code: string, line_total_minor_units: int, occurred_at: string, merchant_id: string|null, merchant_name: string|null} */
final readonly class ItemPriceMovementCalculator
{
    public function __construct(
        private UnitNormalizer $units,
        private Clock $clock,
    ) {}

    /** @return array<string, mixed> */
    public function calculate(User $user, LineItem $current): array
    {
        $common = [
            'formula' => InsightFormula::identity(FormulaName::ItemPriceMovement),
            'calculated_at' => $this->clock->now()->format(DATE_ATOM),
        ];
        if ($current->user_id !== $user->getKey() || $current->canonical_item_id === null || $current->financial_transaction_id === null || $current->receipt_id === null || $current->currency_code === null || $current->line_total_minor_units === null) {
            return [...$common, 'status' => 'insufficient_history', 'reason' => 'current_line_is_not_an_eligible_receipt_purchase'];
        }
        $currentLine = $this->line($current->getKey());
        if ($currentLine === null) {
            return [...$common, 'status' => 'insufficient_history', 'reason' => 'current_line_is_not_posted'];
        }
        $currentQuantity = $this->units->normalize($currentLine['unit_code'], $currentLine['quantity']);
        if ($currentQuantity === null) {
            return [...$common, 'status' => 'insufficient_history', 'reason' => 'current_line_has_no_compatible_normalized_unit'];
        }
        $previous = null;
        foreach ($this->candidateLines($user, $currentLine) as $candidate) {
            $normalized = $this->units->normalize($candidate['unit_code'], $candidate['quantity']);
            if ($normalized !== null && $normalized['dimension'] === $currentQuantity['dimension']) {
                $previous = $candidate;

                break;
            }
        }
        if ($previous === null) {
            return [...$common, 'status' => 'insufficient_history', 'reason' => 'no_previous_compatible_posted_purchase'];
        }
        $previousQuantity = $this->units->normalize($previous['unit_code'], $previous['quantity']);
        if ($previousQuantity === null) {
            return [...$common, 'status' => 'insufficient_history', 'reason' => 'no_previous_compatible_posted_purchase'];
        }
        $currentPrice = BigDecimal::of($currentLine['line_total_minor_units'])->dividedBy($currentQuantity['quantity'], 12, RoundingMode::HalfEven);
        $previousPrice = BigDecimal::of($previous['line_total_minor_units'])->dividedBy($previousQuantity['quantity'], 12, RoundingMode::HalfEven);
        if ($previousPrice->isLessThanOrEqualTo(0)) {
            return [...$common, 'status' => 'insufficient_history', 'reason' => 'previous_unit_price_is_not_positive'];
        }
        $absolute = $currentPrice->minus($previousPrice);
        $percentage = $absolute->dividedBy($previousPrice, 18, RoundingMode::HalfEven)->multipliedBy(100);
        $scope = $currentLine['merchant_id'] !== null && $currentLine['merchant_id'] === $previous['merchant_id'] ? 'same_merchant' : 'cross_merchant';

        return [...$common,
            'status' => 'available',
            'canonical_item_id' => $currentLine['canonical_item_id'],
            'current_merchant_id' => $currentLine['merchant_id'],
            'previous_merchant_id' => $previous['merchant_id'],
            'current_merchant' => $currentLine['merchant_name'],
            'previous_merchant' => $previous['merchant_name'],
            'current_occurrence_at' => $currentLine['occurred_at'],
            'previous_occurrence_at' => $previous['occurred_at'],
            'current_source_quantity' => $currentLine['quantity'],
            'current_source_unit' => $currentLine['unit_code'],
            'previous_source_quantity' => $previous['quantity'],
            'previous_source_unit' => $previous['unit_code'],
            'normalized_quantity_unit' => $currentQuantity['unit'],
            'current_normalized_quantity' => $currentQuantity['quantity'],
            'previous_normalized_quantity' => $previousQuantity['quantity'],
            'current_effective_line_amount_minor_units' => $currentLine['line_total_minor_units'],
            'previous_effective_line_amount_minor_units' => $previous['line_total_minor_units'],
            'current_unit_price_minor_units' => $currentPrice->toScale(12, RoundingMode::HalfEven)->__toString(),
            'previous_unit_price_minor_units' => $previousPrice->toScale(12, RoundingMode::HalfEven)->__toString(),
            'original_currency_code' => $currentLine['currency_code'],
            'absolute_change_minor_units' => $absolute->toScale(12, RoundingMode::HalfEven)->__toString(),
            'percentage_change' => $percentage->toScale(12, RoundingMode::HalfEven)->__toString(),
            'comparison_scope' => $scope,
            'alert_eligible' => $scope === 'same_merchant',
        ];
    }

    /** @return PriceLine|null */
    private function line(string $lineItemId): ?array
    {
        $line = DB::table('line_items')
            ->join('financial_transactions', 'financial_transactions.id', '=', 'line_items.financial_transaction_id')
            ->leftJoin('merchants', 'merchants.id', '=', 'financial_transactions.merchant_id')
            ->where('line_items.id', $lineItemId)
            ->whereNotNull('line_items.receipt_id')
            ->where('financial_transactions.state', 'posted')
            ->whereColumn('financial_transactions.original_currency_code', 'line_items.currency_code')
            ->select('line_items.*', 'financial_transactions.occurred_at', 'financial_transactions.merchant_id', 'merchants.display_name as merchant_name')
            ->first();

        if (! is_object($line)) {
            return null;
        }

        return $this->mapLine((array) $line);
    }

    /**
     * @param  PriceLine  $current
     * @return list<PriceLine>
     */
    private function candidateLines(User $user, array $current): array
    {
        $lines = DB::table('line_items')
            ->join('financial_transactions', 'financial_transactions.id', '=', 'line_items.financial_transaction_id')
            ->leftJoin('merchants', 'merchants.id', '=', 'financial_transactions.merchant_id')
            ->where('line_items.user_id', $user->getKey())
            ->where('line_items.canonical_item_id', $current['canonical_item_id'])
            ->where('line_items.currency_code', $current['currency_code'])
            ->whereNotNull('line_items.receipt_id')
            ->whereNotNull('line_items.financial_transaction_id')
            ->whereNotNull('line_items.quantity')
            ->whereNotNull('line_items.unit_code')
            ->whereNotNull('line_items.line_total_minor_units')
            ->where('financial_transactions.state', 'posted')
            ->whereColumn('financial_transactions.original_currency_code', 'line_items.currency_code')
            ->where(function ($query) use ($current): void {
                $query->where('financial_transactions.occurred_at', '<', $current['occurred_at'])
                    ->orWhere(function ($sameTimestamp) use ($current): void {
                        $sameTimestamp->where('financial_transactions.occurred_at', $current['occurred_at'])->where('line_items.id', '<', $current['id']);
                    });
            })
            ->select('line_items.*', 'financial_transactions.occurred_at', 'financial_transactions.merchant_id', 'merchants.display_name as merchant_name')
            ->orderByDesc('financial_transactions.occurred_at')
            ->orderByDesc('line_items.id')
            ->get();
        $candidates = [];
        foreach ($lines as $line) {
            $candidates[] = $this->mapLine((array) $line);
        }

        return $candidates;
    }

    /**
     * @param  array<string, mixed>  $line
     * @return PriceLine
     */
    private function mapLine(array $line): array
    {
        return [
            'id' => (string) $line['id'],
            'canonical_item_id' => (string) $line['canonical_item_id'],
            'currency_code' => (string) $line['currency_code'],
            'quantity' => (string) $line['quantity'],
            'unit_code' => (string) $line['unit_code'],
            'line_total_minor_units' => (int) $line['line_total_minor_units'],
            'occurred_at' => (string) $line['occurred_at'],
            'merchant_id' => $line['merchant_id'] === null ? null : (string) $line['merchant_id'],
            'merchant_name' => $line['merchant_name'] === null ? null : (string) $line['merchant_name'],
        ];
    }
}
