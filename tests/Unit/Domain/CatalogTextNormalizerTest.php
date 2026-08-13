<?php

use App\Domain\Catalog\CatalogTextNormalizer;

test('normalizes Unicode text for search without changing source values', function (): void {
    $normalizer = new CatalogTextNormalizer;

    expect($normalizer->normalize('  Café—Market  24/7 '))->toBe('café market 24 7');
});

test('only treats identical unit and pack dimensions as comparable', function (): void {
    $normalizer = new CatalogTextNormalizer;

    expect($normalizer->compatibleUnits('g', 'g', 'kg', 'kg'))->toBeTrue()
        ->and($normalizer->compatibleUnits('g', 'ml', 'kg', 'kg'))->toBeFalse()
        ->and($normalizer->compatibleUnits('g', 'g', 'kg', 'g'))->toBeFalse();
});
