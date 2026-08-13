<?php

namespace App\Domain\Currency;

use App\Domain\Currency\ValueObjects\CurrencyCode;
use App\Domain\Currency\ValueObjects\ExchangeRate;
use DateTimeImmutable;

final readonly class ExchangeRateQuote
{
    public function __construct(
        public CurrencyCode $baseCurrency,
        public CurrencyCode $quoteCurrency,
        public ExchangeRate $rate,
        public DateTimeImmutable $rateDate,
        public string $source,
        public DateTimeImmutable $retrievedAt,
    ) {}
}
