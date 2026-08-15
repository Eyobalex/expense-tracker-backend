<?php

namespace App\Domain\Currency\Contracts;

use App\Domain\Currency\ExchangeRateQuote;
use App\Domain\Currency\ValueObjects\CurrencyCode;
use DateTimeImmutable;

interface ExchangeRateProvider
{
    public function fetch(CurrencyCode $baseCurrency, CurrencyCode $quoteCurrency, DateTimeImmutable $rateDate): ExchangeRateQuote;
}
