<?php

namespace App\Domain\Reporting;

use DateTimeImmutable;
use DateTimeZone;

final readonly class ReportFilter
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public array $filters,
        public string $timezone,
        public string $baseCurrencyCode,
    ) {}

    public function from(): ?DateTimeImmutable
    {
        return $this->boundary('from', false);
    }

    public function until(): ?DateTimeImmutable
    {
        return $this->boundary('to', true);
    }

    /** @return array<string, mixed> */
    public function metadata(): array
    {
        return [
            'filters' => $this->filters,
            'timezone' => $this->timezone,
            'base_currency_code' => $this->baseCurrencyCode,
            'from' => $this->from()?->format(DATE_ATOM),
            'until' => $this->until()?->format(DATE_ATOM),
        ];
    }

    private function boundary(string $key, bool $endOfDay): ?DateTimeImmutable
    {
        $value = $this->filters[$key] ?? null;
        if (! is_string($value) || $value === '') {
            return null;
        }

        $date = new DateTimeImmutable($value, new DateTimeZone($this->timezone));

        return $endOfDay
            ? $date->setTime(0, 0)->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))
            : $date->setTime(0, 0)->setTimezone(new DateTimeZone('UTC'));
    }
}
