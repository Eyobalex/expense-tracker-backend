<?php

use App\Application\Receipts\ReceiptOcrNormalizer;
use App\Domain\Receipts\ValueObjects\OcrResult;

function normalizedReceipt(string $text): array
{
    return app(ReceiptOcrNormalizer::class)->normalize(new OcrResult([], $text, [], 'fixture', 'pp-ocrv6-fixture'));
}

test('parses supported English and Amharic scripts with explicit ETB or USD currency evidence', function (): void {
    $normalized = normalizedReceipt("Merchant: ማርኬት\nETB\nTotal: 1,250.50\n2026-08-11");

    expect($normalized['locale'])->toBe('en-am')
        ->and($normalized['parser_version'])->toBe('locale-matrix-v1')
        ->and($normalized['parsed_fields'])->toMatchArray([
            'merchant' => 'ማርኬት', 'currency_code' => 'ETB', 'total_decimal' => '1250.50', 'occurred_on' => '2026-08-11',
            'timezone_interpretation' => 'date_only_pending_confirmation',
        ])
        ->and($normalized['ambiguities'])->toBe([]);
});

test('parses explicitly supported localized number forms', function (string $number, string $expected): void {
    $normalized = normalizedReceipt("USD\nTotal: {$number}");

    expect($normalized['parsed_fields']['currency_code'])->toBe('USD')
        ->and($normalized['parsed_fields']['total_decimal'])->toBe($expected)
        ->and($normalized['ambiguities'])->toBe([]);
})->with([
    ['1,250.50', '1250.50'],
    ['1.250,50', '1250.50'],
    ['1 250,50', '1250.50'],
]);

test('preserves ambiguous dates and currency symbols for review without guessing', function (): void {
    $normalized = normalizedReceipt("Merchant: Market\n$\nTotal: 1,250\n11/08/2026");

    expect($normalized['status'])->toBe('needs_review')
        ->and($normalized['parsed_fields'])->toMatchArray(['merchant' => 'Market'])
        ->and($normalized['parsed_fields'])->not->toHaveKey('currency_code')
        ->and($normalized['parsed_fields'])->not->toHaveKey('total_decimal')
        ->and($normalized['parsed_fields'])->not->toHaveKey('occurred_on')
        ->and($normalized['ambiguities'])->toContain('ambiguous_currency_symbol', 'ambiguous_number_format', 'ambiguous_numeric_date');
});
