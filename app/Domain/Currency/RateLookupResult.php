<?php

namespace App\Domain\Currency;

use App\Domain\Currency\Enums\RateLookupStatus;

final readonly class RateLookupResult
{
    private function __construct(
        public RateLookupStatus $status,
        public ?ExchangeRateQuote $quote,
    ) {}

    public static function exact(ExchangeRateQuote $quote): self
    {
        return new self(RateLookupStatus::Exact, $quote);
    }

    public static function latestValid(ExchangeRateQuote $quote): self
    {
        return new self(RateLookupStatus::LatestValid, $quote);
    }

    public static function stale(ExchangeRateQuote $quote): self
    {
        return new self(RateLookupStatus::Stale, $quote);
    }

    public static function unavailable(): self
    {
        return new self(RateLookupStatus::Unavailable, null);
    }

    public function hasRate(): bool
    {
        return $this->quote !== null;
    }
}
