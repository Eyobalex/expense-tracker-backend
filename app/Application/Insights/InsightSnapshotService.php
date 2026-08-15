<?php

namespace App\Application\Insights;

use App\Domain\Insights\Enums\FormulaName;
use App\Domain\Insights\InsightFormula;
use App\Models\InsightSnapshot;
use App\Models\User;
use DateTimeInterface;

final class InsightSnapshotService
{
    /**
     * @param  array<string, mixed>  $inputs
     * @param  array<string, mixed>  $result
     */
    public function persist(User $user, FormulaName $formula, string $timezone, ?string $baseCurrencyCode, DateTimeInterface $calculatedAt, array $inputs, array $result, ?DateTimeInterface $coveredFrom = null, ?DateTimeInterface $coveredUntil = null): InsightSnapshot
    {
        return $user->insightSnapshots()->create([
            'formula_name' => $formula->value,
            'formula_version' => InsightFormula::Version,
            'timezone' => $timezone,
            'base_currency_code' => $baseCurrencyCode,
            'covered_from_at' => $coveredFrom,
            'covered_until_at' => $coveredUntil,
            'calculated_at' => $calculatedAt,
            'inputs' => $inputs,
            'result' => $result,
        ]);
    }
}
