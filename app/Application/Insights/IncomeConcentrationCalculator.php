<?php

namespace App\Application\Insights;

use App\Domain\Catalog\CatalogTextNormalizer;
use App\Domain\Insights\Enums\FormulaName;
use App\Domain\Insights\InsightFormula;
use App\Domain\Shared\Time\Clock;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

final readonly class IncomeConcentrationCalculator
{
    public function __construct(
        private Clock $clock,
        private CatalogTextNormalizer $normalizer,
    ) {}

    /** @return array<string, mixed> */
    public function calculate(User $user): array
    {
        $calculatedAt = $this->clock->now();
        $timezone = new \DateTimeZone($user->timezone);
        $localNow = $calculatedAt->setTimezone($timezone);
        $coveredFrom = $localNow->modify('first day of this month')->setTime(0, 0)->modify('-11 months');
        $transactions = DB::table('financial_transactions')
            ->leftJoin('merchants', 'merchants.id', '=', 'financial_transactions.merchant_id')
            ->where('financial_transactions.user_id', $user->getKey())
            ->where('financial_transactions.state', 'posted')
            ->where('financial_transactions.type', 'income')
            ->where('financial_transactions.occurred_at', '>=', $coveredFrom)
            ->where('financial_transactions.occurred_at', '<=', $calculatedAt)
            ->select([
                'financial_transactions.base_amount_minor_units',
                'financial_transactions.raw_merchant_text',
                'merchants.display_name as canonical_source',
            ])
            ->get();
        $sources = [];
        foreach ($transactions as $transaction) {
            $source = $this->sourceName($transaction->canonical_source, $transaction->raw_merchant_text);
            $sources[$source] = ($sources[$source] ?? 0) + (int) $transaction->base_amount_minor_units;
        }
        $total = array_sum($sources);
        $common = [
            'formula' => InsightFormula::identity(FormulaName::IncomeConcentration),
            'covered_period' => ['period_start_at' => $coveredFrom->format(DATE_ATOM), 'period_end_at' => $calculatedAt->format(DATE_ATOM)],
            'timezone' => $user->timezone,
            'base_currency_code' => $user->base_currency_code,
            'calculated_at' => $calculatedAt->format(DATE_ATOM),
            'total_qualifying_income_minor_units' => $total,
        ];
        if ($total === 0) {
            return [...$common, 'status' => 'not_available', 'reason' => 'no_qualifying_income', 'sources' => [], 'concentration_hhi' => null, 'effective_source_count' => null, 'largest_source_share' => null, 'unattributed_income_minor_units' => 0, 'unattributed_share' => null, 'classification' => null];
        }
        ksort($sources);
        $hhi = BigDecimal::zero();
        $sourceResults = [];
        $largestShare = BigDecimal::zero();
        foreach ($sources as $source => $amount) {
            $share = BigDecimal::of($amount)->dividedBy($total, 18, RoundingMode::HalfEven);
            $hhi = $hhi->plus($share->multipliedBy($share));
            $largestShare = $largestShare->isGreaterThan($share) ? $largestShare : $share;
            $sourceResults[] = ['source' => $source, 'income_minor_units' => $amount, 'share' => $share->toScale(12, RoundingMode::HalfEven)->__toString()];
        }
        $unattributed = $sources['Unattributed'] ?? 0;
        $unattributedShare = BigDecimal::of($unattributed)->dividedBy($total, 18, RoundingMode::HalfEven);
        $classification = $unattributedShare->isGreaterThan('0.20') ? null : $this->classification($hhi);

        return [...$common,
            'status' => $classification === null ? 'insufficient_attribution' : 'available',
            'reason' => $classification === null ? 'unattributed_income_exceeds_threshold' : null,
            'sources' => $sourceResults,
            'concentration_hhi' => $hhi->toScale(12, RoundingMode::HalfEven)->__toString(),
            'effective_source_count' => BigDecimal::one()->dividedBy($hhi, 12, RoundingMode::HalfEven)->__toString(),
            'largest_source_share' => $largestShare->toScale(12, RoundingMode::HalfEven)->__toString(),
            'unattributed_income_minor_units' => $unattributed,
            'unattributed_share' => $unattributedShare->toScale(12, RoundingMode::HalfEven)->__toString(),
            'classification' => $classification,
        ];
    }

    private function sourceName(?string $canonical, ?string $normalized): string
    {
        if (is_string($canonical) && trim($canonical) !== '') {
            return trim($canonical);
        }
        if (is_string($normalized) && trim($normalized) !== '') {
            return $this->normalizer->normalize($normalized);
        }

        return 'Unattributed';
    }

    private function classification(BigDecimal $hhi): string
    {
        if ($hhi->isLessThanOrEqualTo('0.15')) {
            return 'diversified';
        }
        if ($hhi->isLessThanOrEqualTo('0.25')) {
            return 'moderately_concentrated';
        }

        return 'concentrated';
    }
}
