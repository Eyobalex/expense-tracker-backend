<?php

namespace App\Domain\Catalog;

use Normalizer;

final class CatalogTextNormalizer
{
    public const VERSION = 'catalog-text-v1';

    public function normalize(string $value): string
    {
        $normalized = Normalizer::normalize($value, Normalizer::FORM_KC) ?? $value;
        $normalized = mb_strtolower(trim($normalized));
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normalized) ?? '';

        return trim((string) preg_replace('/\s+/u', ' ', $normalized));
    }

    public function similarity(string $left, string $right): float
    {
        if ($left === $right) {
            return 1.0;
        }
        $maximumLength = max(mb_strlen($left), mb_strlen($right));
        if ($maximumLength === 0) {
            return 0.0;
        }
        $distance = levenshtein($left, $right);

        return max(0.0, 1 - ($distance / $maximumLength));
    }

    public function compatibleUnits(?string $leftUnit, ?string $rightUnit, ?string $leftPackUnit, ?string $rightPackUnit): bool
    {
        return $leftUnit === $rightUnit && $leftPackUnit === $rightPackUnit;
    }
}
