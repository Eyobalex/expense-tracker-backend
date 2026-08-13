<?php

namespace App\Application\Receipts;

use App\Domain\Receipts\ValueObjects\OcrResult;

final class ReceiptOcrNormalizer
{
    /** @return array<string, mixed> */
    public function normalize(OcrResult $result): array
    {
        $text = trim($result->text);
        $ambiguities = [];
        $locale = $this->detectLocale($text, $ambiguities);
        $currency = $this->extractCurrency($text, $ambiguities);
        $date = $this->extractDate($text, $ambiguities);
        $merchant = $this->extractMerchant($text);
        $total = $this->extractLabeledTotal($text, $ambiguities);

        $parsedFields = array_filter([
            'merchant' => $merchant,
            'currency_code' => $currency,
            'total_decimal' => $total,
            'occurred_on' => $date,
            'occurred_at' => null,
            'timezone_interpretation' => $date === null ? null : config('receipts.parser.timezone_interpretation'),
        ], static fn (mixed $value): bool => $value !== null);

        return [
            'raw_text' => $result->text,
            'receipt_type' => 'unknown',
            'status' => 'needs_review',
            'locale' => $locale,
            'parser_version' => config('receipts.parser.version'),
            'ambiguities' => array_values(array_unique($ambiguities)),
            'parsed_fields' => $parsedFields === [] ? (object) [] : $parsedFields,
        ];
    }

    /** @param array<int, string> $ambiguities */
    private function detectLocale(string $text, array &$ambiguities): string
    {
        $hasEthiopic = preg_match('/\p{Ethiopic}/u', $text) === 1;
        $hasLatin = preg_match('/\p{Latin}/u', $text) === 1;

        if (preg_match('/[^\p{Latin}\p{Ethiopic}\p{N}\p{P}\p{Z}\p{Sc}\p{M}\r\n\t]/u', $text) === 1) {
            $ambiguities[] = 'unsupported_script';
        }

        return match (true) {
            $hasEthiopic && $hasLatin => 'en-am',
            $hasEthiopic => 'am',
            default => 'en',
        };
    }

    /** @param array<int, string> $ambiguities */
    private function extractCurrency(string $text, array &$ambiguities): ?string
    {
        $hasEtb = preg_match('/\bETB\b|ethiopian\s+birr|ብር/ui', $text) === 1;
        $hasUsd = preg_match('/\bUSD\b|\bUS\s+dollars?\b|\bunited\s+states\s+dollars?\b/ui', $text) === 1;

        if ($hasEtb && $hasUsd) {
            $ambiguities[] = 'multiple_supported_currencies';

            return null;
        }
        if ($hasEtb) {
            return 'ETB';
        }
        if ($hasUsd) {
            return 'USD';
        }
        if (preg_match('/[$€£]|\bBr\b/ui', $text) === 1) {
            $ambiguities[] = 'ambiguous_currency_symbol';
        }

        return null;
    }

    /** @param array<int, string> $ambiguities */
    private function extractDate(string $text, array &$ambiguities): ?string
    {
        if (preg_match('/\b\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4}\b/', $text) === 1) {
            $ambiguities[] = 'ambiguous_numeric_date';
        }

        $formats = [
            '/\b(\d{4}-\d{2}-\d{2})\b/' => 'Y-m-d',
            '/\b(\d{1,2}\s+(?:Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:t(?:ember)?)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)\s+\d{4})\b/i' => 'j F Y',
            '/\b((?:Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:t(?:ember)?)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)\s+\d{1,2},?\s+\d{4})\b/i' => 'F j Y',
        ];

        foreach ($formats as $pattern => $format) {
            if (preg_match($pattern, $text, $matches) !== 1) {
                continue;
            }
            $parsed = \DateTimeImmutable::createFromFormat('!'.$format, $matches[1]);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($parsed instanceof \DateTimeImmutable && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $parsed->format('Y-m-d');
            }
        }

        return null;
    }

    private function extractMerchant(string $text): ?string
    {
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            if (preg_match('/^\s*(?:merchant|store|seller|business|ሱቅ|ንግድ|ሻጭ)\s*[:#-]\s*(.+?)\s*$/iu', $line, $matches) !== 1) {
                continue;
            }
            $merchant = trim($matches[1]);
            if ($merchant !== '') {
                return $merchant;
            }
        }

        return null;
    }

    /** @param array<int, string> $ambiguities */
    private function extractLabeledTotal(string $text, array &$ambiguities): ?string
    {
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            if (preg_match('/^\s*(?:grand\s+total|total|ጠቅላላ)\s*[:#-]?\s*(?:ETB|USD|ethiopian\s+birr|us\s+dollars?)?\s*([\d][\d.,\s]*)\s*$/iu', $line, $matches) !== 1) {
                continue;
            }

            return $this->normalizeDecimal($matches[1], $ambiguities);
        }

        return null;
    }

    /** @param array<int, string> $ambiguities */
    private function normalizeDecimal(string $value, array &$ambiguities): ?string
    {
        $value = trim($value);
        if (preg_match('/^\d{1,3}(?:,\d{3})+\.\d{2}$/', $value) === 1) {
            return str_replace(',', '', $value);
        }
        if (preg_match('/^\d{1,3}(?:\.\d{3})+,\d{2}$/', $value) === 1) {
            return str_replace(',', '.', str_replace('.', '', $value));
        }
        if (preg_match('/^\d{1,3}(?: \d{3})+,\d{2}$/', $value) === 1) {
            return str_replace(',', '.', str_replace(' ', '', $value));
        }
        if (preg_match('/^\d+(?:\.\d{2})?$/', $value) === 1) {
            return $value;
        }

        $ambiguities[] = 'ambiguous_number_format';

        return null;
    }
}
