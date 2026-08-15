<?php

namespace App\Application\Insights;

use App\Domain\Shared\Time\MonthlyPeriod;
use DateTimeImmutable;
use DateTimeInterface;

final readonly class InsightPeriod
{
    public function __construct(
        public MonthlyPeriod $budgetPeriod,
        public DateTimeImmutable $calculatedAt,
    ) {}

    public static function forMonth(MonthlyPeriod $budgetPeriod, DateTimeInterface $calculatedAt): self
    {
        return new self($budgetPeriod, DateTimeImmutable::createFromInterface($calculatedAt));
    }

    public function localCalculatedAt(): DateTimeImmutable
    {
        return $this->calculatedAt->setTimezone($this->budgetPeriod->timezone);
    }

    public function isCurrent(): bool
    {
        return $this->budgetPeriod->contains($this->calculatedAt);
    }

    public function remainingDaysIncludingToday(): int
    {
        $today = $this->localCalculatedAt()->setTime(0, 0);
        $periodEnd = $this->budgetPeriod->endsAt();

        return max(1, (int) $today->diff($periodEnd)->format('%a'));
    }

    public function remainingDaysAfterToday(): int
    {
        $tomorrow = $this->localCalculatedAt()->modify('+1 day')->setTime(0, 0);
        $periodEnd = $this->budgetPeriod->endsAt();

        return max(0, (int) $tomorrow->diff($periodEnd)->format('%r%a'));
    }

    public function elapsedCalendarDays(): int
    {
        $today = $this->localCalculatedAt()->setTime(0, 0);

        return max(1, (int) $this->budgetPeriod->startsAt()->diff($today)->format('%a') + 1);
    }

    public function localDayStart(): DateTimeImmutable
    {
        return $this->localCalculatedAt()->setTime(0, 0);
    }

    public function localDayEnd(): DateTimeImmutable
    {
        return $this->localDayStart()->modify('+1 day');
    }
}
