<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * A fixed-instant clock for tests, so SLA calculations can be exercised
 * across specific moments (e.g. a DST boundary) deterministically.
 */
class FrozenClock implements Clock
{
    private CarbonImmutable $instant;

    public function __construct(CarbonImmutable|string $instant)
    {
        $this->instant = CarbonImmutable::parse($instant)->setTimezone('UTC');
    }

    public function now(): CarbonImmutable
    {
        return $this->instant;
    }

    public function set(CarbonImmutable|string $instant): void
    {
        $this->instant = CarbonImmutable::parse($instant)->setTimezone('UTC');
    }
}
