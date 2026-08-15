<?php

namespace App\Application\Currency;

use App\Domain\Currency\Contracts\HistoricalExchangeRateLookup;
use App\Domain\Currency\Enums\RateLookupStatus;
use App\Domain\Currency\ValueObjects\CurrencyCode;
use App\Domain\Shared\Exceptions\DomainErrorCode;
use App\Domain\Shared\Exceptions\DomainException;
use App\Models\FinancialTransaction;
use App\Models\User;
use DateTimeImmutable;

final readonly class TransactionRateLockingService
{
    public function __construct(private HistoricalExchangeRateLookup $rates, private CurrencyPolicy $policy) {}

    public function lockForPosting(User $user, FinancialTransaction $transaction): void
    {
        if ($transaction->original_currency_code === $user->base_currency_code) {
            return;
        }

        $rateDate = new DateTimeImmutable(($transaction->rate_date ?? $transaction->occurred_at)->toDateString());
        $lookup = $this->rates->find(
            CurrencyCode::fromString($transaction->original_currency_code),
            CurrencyCode::fromString($user->base_currency_code),
            $rateDate,
        );
        if ($transaction->used_rate === null) {
            if (! $lookup->hasRate() || $lookup->status === RateLookupStatus::Stale) {
                throw DomainException::for(DomainErrorCode::InvalidRateLock, 'No fresh exchange rate is available; provide an audited manual override.');
            }
            $quote = $lookup->quote;
            $transaction->forceFill([
                'reference_rate' => $quote->rate->decimal(),
                'used_rate' => $quote->rate->decimal(),
                'rate_date' => $quote->rateDate->format('Y-m-d'),
                'rate_source' => $quote->source,
                'rounding_mode' => $this->policy->roundingMode()->value,
            ])->save();

            return;
        }

        $referenceRate = $transaction->reference_rate ?? ($lookup->hasRate() ? $lookup->quote->rate->decimal() : null);
        if ($referenceRate === null || trim((string) $transaction->rate_override_reason) === '') {
            throw DomainException::for(DomainErrorCode::InvalidRateLock, 'A manual exchange-rate override requires a reference rate and reason.');
        }
        $transaction->forceFill([
            'reference_rate' => $referenceRate,
            'rate_date' => $transaction->rate_date ?? $rateDate->format('Y-m-d'),
            'rate_source' => 'manual_override',
            'rounding_mode' => $this->policy->roundingMode()->value,
        ])->save();
    }
}
