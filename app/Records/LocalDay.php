<?php

namespace App\Records;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class LocalDay
{
    public function __construct(private string $timezone) {}

    public static function forTimezone(string $timezone): self
    {
        return new self($timezone);
    }

    public function startOfDayUtc(CarbonInterface $now): CarbonImmutable
    {
        return CarbonImmutable::parse($now)->timezone($this->timezone)->startOfDay()->utc();
    }

    public function startOfNextDayUtc(CarbonInterface $now): CarbonImmutable
    {
        return CarbonImmutable::parse($now)->timezone($this->timezone)->startOfDay()->addDay()->utc();
    }

    public function dateOnlyToUtc(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, $this->timezone)->startOfDay()->utc();
    }

    public function isScheduledOnLocalDay(CarbonInterface $scheduledAtUtc, CarbonInterface $now): bool
    {
        $scheduledAt = CarbonImmutable::parse($scheduledAtUtc)->utc();

        return $scheduledAt->gte($this->startOfDayUtc($now))
            && $scheduledAt->lt($this->startOfNextDayUtc($now));
    }

    public function isScheduledBeforeLocalDay(CarbonInterface $scheduledAtUtc, CarbonInterface $now): bool
    {
        return CarbonImmutable::parse($scheduledAtUtc)->utc()->lt($this->startOfDayUtc($now));
    }
}
