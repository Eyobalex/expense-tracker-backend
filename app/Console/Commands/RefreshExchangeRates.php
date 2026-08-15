<?php

namespace App\Console\Commands;

use App\Application\Currency\ExchangeRateRefreshService;
use App\Jobs\RefreshExchangeRates as RefreshExchangeRatesJob;
use DateTimeImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('fx:refresh-rates {--date= : Refresh a YYYY-MM-DD provider rate date.} {--sync : Refresh inline instead of dispatching the retryable job.}')]
#[Description('Refresh the configured USD to ETB provider rate.')]
class RefreshExchangeRates extends Command
{
    public function handle(ExchangeRateRefreshService $rates): int
    {
        $date = (string) ($this->option('date') ?? now('UTC')->toDateString());
        $rateDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (! $rateDate instanceof DateTimeImmutable || $rateDate->format('Y-m-d') !== $date) {
            $this->error('The --date option must use YYYY-MM-DD.');

            return self::FAILURE;
        }
        if ($this->option('sync')) {
            $rates->refresh($rateDate);
            $this->info('Exchange rate refreshed.');
        } else {
            RefreshExchangeRatesJob::dispatch($date);
            $this->info('Exchange-rate refresh job dispatched.');
        }

        return self::SUCCESS;
    }
}
