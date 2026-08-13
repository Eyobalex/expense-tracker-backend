<?php

use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Shared\Time\MonthlyPeriod;

it('snapshots timezone boundaries across a DST month', function (): void {
    $period = MonthlyPeriod::forMonth(2026, 3, 'America/New_York');

    expect($period->startsAt()->format('P'))->toBe('-05:00')
        ->and($period->endsAt()->format('P'))->toBe('-04:00')
        ->and($period->contains(new DateTimeImmutable('2026-03-31 23:59:59', new DateTimeZone('America/New_York'))))->toBeTrue();
});

it('rejects an invalid month deterministically', function (): void {
    expect(fn (): MonthlyPeriod => MonthlyPeriod::forMonth(2026, 13, 'UTC'))->toThrow(DomainException::class);
});
