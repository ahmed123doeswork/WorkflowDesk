<?php

namespace Tests\Unit;

use App\Support\BusinessCalendar;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class BusinessCalendarTest extends TestCase
{
    public function test_adding_minutes_within_the_same_business_day(): void
    {
        $calendar = new BusinessCalendar('UTC');

        // Monday 2026-03-02, 10:00 UTC (a Monday) + 90 minutes.
        $result = $calendar->addBusinessMinutes(
            CarbonImmutable::parse('2026-03-02 10:00:00', 'UTC'),
            90,
        );

        $this->assertSame('2026-03-02 11:30:00', $result->format('Y-m-d H:i:s'));
    }

    public function test_rolls_from_outside_business_hours_to_the_next_business_start(): void
    {
        $calendar = new BusinessCalendar('UTC');

        // Monday 2026-03-02, 20:00 UTC (after hours) + 30 minutes
        // should land 30 minutes into Tuesday's business day.
        $result = $calendar->addBusinessMinutes(
            CarbonImmutable::parse('2026-03-02 20:00:00', 'UTC'),
            30,
        );

        $this->assertSame('2026-03-03 09:30:00', $result->format('Y-m-d H:i:s'));
    }

    public function test_skips_weekends(): void
    {
        $calendar = new BusinessCalendar('UTC');

        // Friday 2026-03-06, 16:30 UTC (30 minutes left in the day) + 60 minutes
        // should skip the weekend and land 60 minutes into Monday.
        $result = $calendar->addBusinessMinutes(
            CarbonImmutable::parse('2026-03-06 16:30:00', 'UTC'),
            90,
        );

        $this->assertSame('2026-03-09 10:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function test_skips_holidays(): void
    {
        $calendar = new BusinessCalendar('UTC', ['2026-03-03']);

        // Monday 2026-03-02, 16:30 UTC (30 minutes left) + 60 minutes, with
        // Tuesday 2026-03-03 as a holiday, should land 60 minutes into
        // Wednesday.
        $result = $calendar->addBusinessMinutes(
            CarbonImmutable::parse('2026-03-02 16:30:00', 'UTC'),
            90,
        );

        $this->assertSame('2026-03-04 10:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function test_spring_forward_dst_boundary_is_handled_correctly(): void
    {
        $calendar = new BusinessCalendar('America/New_York');

        // Friday 2026-03-06 16:30 local (EST, UTC-5) + 90 minutes crosses
        // the weekend into Monday 2026-03-09, after the US spring-forward
        // transition on Sunday 2026-03-08 (EST -> EDT, UTC-4).
        $result = $calendar->addBusinessMinutes(
            CarbonImmutable::parse('2026-03-06 16:30:00', 'America/New_York'),
            90,
        );

        $this->assertSame('2026-03-09 10:00:00', $result->setTimezone('America/New_York')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-09T14:00:00+00:00', $result->toIso8601String());
    }

    public function test_fall_back_dst_boundary_is_handled_correctly(): void
    {
        $calendar = new BusinessCalendar('America/New_York');

        // Friday 2026-10-30 16:30 local (EDT, UTC-4) + 90 minutes crosses
        // the weekend into Monday 2026-11-02, after the US fall-back
        // transition on Sunday 2026-11-01 (EDT -> EST, UTC-5).
        $result = $calendar->addBusinessMinutes(
            CarbonImmutable::parse('2026-10-30 16:30:00', 'America/New_York'),
            90,
        );

        $this->assertSame('2026-11-02 10:00:00', $result->setTimezone('America/New_York')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-02T15:00:00+00:00', $result->toIso8601String());
    }
}
