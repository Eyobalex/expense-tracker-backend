<?php

namespace App\Domain\Currency\Contracts;

use App\Domain\Currency\RateLookupResult;
use App\Domain\Currency\ValueObjects\CurrencyCode;
use DateTimeImmutable;

interface HistoricalExchangeRateLookup
{
    public function find(CurrencyCode $baseCurrency, CurrencyCode $quoteCurrency, DateTimeImmutable $rateDate): RateLookupResult;
}
