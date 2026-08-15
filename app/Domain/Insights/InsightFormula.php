<?php

namespace App\Domain\Insights;

use App\Domain\Insights\Enums\FormulaName;

final class InsightFormula
{
    public const int Version = 1;

    /** @return array{name: string, version: int} */
    public static function identity(FormulaName $formula): array
    {
        return ['name' => $formula->value, 'version' => self::Version];
    }
}
