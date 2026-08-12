<?php

namespace App\Domain\Shared\Time;

use App\Domain\Shared\Exceptions\DomainErrorCode;
use App\Domain\Shared\Exceptions\DomainException;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use JsonSerializable;

final readonly class MonthlyPeriod implements JsonSerializable
{
    private function __construct(
        public int $year,
        public int $month,
        public DateTimeZone $timezone,
    ) {}

    public static function forMonth(int $year, int $month, string $timezone): self
    {
        if ($month < 1 || $month > 12) {
            throw DomainException::for(DomainErrorCode::InvalidPeriod, 'A period month must be between 1 and 12.');
        }

        try {
            $zone = new DateTimeZone($timezone);
        } catch (\Exception) {
            throw DomainException::for(DomainErrorCode::InvalidTimezone, 'A valid IANA timezone is required.');
        }

        return new self($year, $month, $zone);
    }

    public static function containing(DateTimeInterface $instant, string $timezone): self
    {
        try {
            $zone = new DateTimeZone($timezone);
        } catch (\Exception) {
            throw DomainException::for(DomainErrorCode::InvalidTimezone, 'A valid IANA timezone is required.');
        }

        $local = DateTimeImmutable::createFromInterface($instant)->setTimezone($zone);

        return new self((int) $local->format('Y'), (int) $local->format('n'), $zone);
    }

    public function startsAt(): DateTimeImmutable
    {
        return new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $this->year, $this->month), $this->timezone);
    }

    public function endsAt(): DateTimeImmutable
    {
        return $this->startsAt()->modify('first day of next month')->setTime(0, 0);
    }

    public function contains(DateTimeInterface $instant): bool
    {
        $normalized = DateTimeImmutable::createFromInterface($instant);

        return $normalized >= $this->startsAt() && $normalized < $this->endsAt();
    }

    public function jsonSerialize(): array
    {
        return [
            'timezone' => $this->timezone->getName(),
            'period_start_at' => $this->startsAt()->format(DateTimeInterface::ATOM),
            'period_end_at' => $this->endsAt()->format(DateTimeInterface::ATOM),
        ];
    }
}
