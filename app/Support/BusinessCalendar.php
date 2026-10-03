<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Walks forward in business time (Mon-Fri, within fixed working hours, minus
 * holidays) for a given timezone. Everything happens in local wall-clock
 * time via Carbon's timezone-aware arithmetic, which is what makes this
 * correct across DST transitions: "9:00 local" stays "9:00 local" even when
 * the UTC offset either side of the transition differs.
 */
class BusinessCalendar
{
    private const START_HOUR = 9;

    private const END_HOUR = 17;

    /**
     * @param  list<string>  $holidays  Y-m-d dates, in the calendar's own timezone.
     */
    public function __construct(
        private readonly string $timezone,
        private readonly array $holidays = [],
    ) {}

    public function addBusinessMinutes(CarbonImmutable $start, int $minutes): CarbonImmutable
    {
        $cursor = $this->rollForwardToBusinessMoment($start->setTimezone($this->timezone));
        $remaining = $minutes;

        while ($remaining > 0) {
            $endOfDay = $cursor->setTime(self::END_HOUR, 0, 0);
            $availableToday = $cursor->diffInMinutes($endOfDay, false);

            if ($remaining <= $availableToday) {
                $cursor = $cursor->addMinutes($remaining);
                $remaining = 0;
            } else {
                $remaining -= $availableToday;
                $cursor = $this->nextBusinessDayStart($cursor);
            }
        }

        return $cursor->setTimezone('UTC');
    }

    private function isBusinessDay(CarbonImmutable $day): bool
    {
        return $day->isWeekday() && ! in_array($day->format('Y-m-d'), $this->holidays, true);
    }

    private function rollForwardToBusinessMoment(CarbonImmutable $moment): CarbonImmutable
    {
        if (! $this->isBusinessDay($moment)) {
            return $this->nextBusinessDayStart($moment);
        }

        $startOfDay = $moment->setTime(self::START_HOUR, 0, 0);
        $endOfDay = $moment->setTime(self::END_HOUR, 0, 0);

        if ($moment->lessThan($startOfDay)) {
            return $startOfDay;
        }

        if ($moment->greaterThanOrEqualTo($endOfDay)) {
            return $this->nextBusinessDayStart($moment);
        }

        return $moment;
    }

    private function nextBusinessDayStart(CarbonImmutable $from): CarbonImmutable
    {
        $next = $from->addDay()->setTime(self::START_HOUR, 0, 0);

        while (! $this->isBusinessDay($next)) {
            $next = $next->addDay();
        }

        return $next;
    }
}
