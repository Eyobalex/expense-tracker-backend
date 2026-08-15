<?php

namespace App\Domain\Insights;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class UnitNormalizer
{
    /**
     * @return array{dimension: string, quantity: string, unit: string}|null
     */
    public function normalize(?string $unitCode, ?string $quantity): ?array
    {
        if ($unitCode === null || $quantity === null || BigDecimal::of($quantity)->isLessThanOrEqualTo(0)) {
            return null;
        }

        return match (mb_strtolower(trim($unitCode))) {
            'g', 'gram', 'grams' => ['dimension' => 'mass', 'quantity' => BigDecimal::of($quantity)->toScale(6, RoundingMode::Unnecessary)->__toString(), 'unit' => 'g'],
            'kg', 'kilogram', 'kilograms' => ['dimension' => 'mass', 'quantity' => BigDecimal::of($quantity)->multipliedBy(1000)->toScale(6, RoundingMode::Unnecessary)->__toString(), 'unit' => 'g'],
            'ml', 'milliliter', 'milliliters' => ['dimension' => 'volume', 'quantity' => BigDecimal::of($quantity)->toScale(6, RoundingMode::Unnecessary)->__toString(), 'unit' => 'ml'],
            'l', 'litre', 'liter', 'liters', 'litres' => ['dimension' => 'volume', 'quantity' => BigDecimal::of($quantity)->multipliedBy(1000)->toScale(6, RoundingMode::Unnecessary)->__toString(), 'unit' => 'ml'],
            'each', 'ea', 'unit', 'units' => ['dimension' => 'count', 'quantity' => BigDecimal::of($quantity)->toScale(6, RoundingMode::Unnecessary)->__toString(), 'unit' => 'each'],
            default => null,
        };
    }
}
