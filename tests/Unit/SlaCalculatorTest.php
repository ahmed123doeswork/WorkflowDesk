<?php

namespace Tests\Unit;

use App\Enums\Priority;
use App\Enums\SlaStatus;
use App\Models\Enquiry;
use App\Models\Tenant;
use App\Services\SlaCalculator;
use App\Support\FrozenClock;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class SlaCalculatorTest extends TestCase
{
    public function test_due_dates_are_computed_in_the_tenant_timezone_and_returned_in_utc(): void
    {
        $clock = new FrozenClock('2026-03-02 10:00:00');
        $sla = new SlaCalculator($clock);

        $tenant = new Tenant(['timezone' => 'UTC']);

        $dueDates = $sla->dueDates($tenant, Priority::Urgent);

        // urgent: 30 min response, 240 min resolution, from 10:00 UTC Monday.
        $this->assertSame('2026-03-02T10:30:00+00:00', $dueDates['response_due_at']->toIso8601String());
        $this->assertSame('2026-03-02T14:00:00+00:00', $dueDates['resolution_due_at']->toIso8601String());
    }

    public function test_status_is_on_track_when_well_before_due_dates(): void
    {
        $clock = new FrozenClock('2026-03-02 10:00:00');
        $sla = new SlaCalculator($clock);

        $enquiry = new Enquiry([
            'response_due_at' => CarbonImmutable::parse('2026-03-02 12:00:00'),
            'resolution_due_at' => CarbonImmutable::parse('2026-03-02 16:00:00'),
        ]);

        $this->assertSame(SlaStatus::OnTrack, $sla->statusFor($enquiry));
    }

    public function test_status_is_at_risk_within_the_buffer_before_a_due_date(): void
    {
        $clock = new FrozenClock('2026-03-02 11:30:00');
        $sla = new SlaCalculator($clock);

        $enquiry = new Enquiry([
            'response_due_at' => CarbonImmutable::parse('2026-03-02 12:00:00'), // 30 min away
            'resolution_due_at' => CarbonImmutable::parse('2026-03-02 16:00:00'),
        ]);

        $this->assertSame(SlaStatus::AtRisk, $sla->statusFor($enquiry));
    }

    public function test_status_is_breached_once_past_the_resolution_due_date(): void
    {
        $clock = new FrozenClock('2026-03-02 16:01:00');
        $sla = new SlaCalculator($clock);

        $enquiry = new Enquiry([
            'responded_at' => CarbonImmutable::parse('2026-03-02 10:05:00'),
            'response_due_at' => CarbonImmutable::parse('2026-03-02 10:30:00'),
            'resolution_due_at' => CarbonImmutable::parse('2026-03-02 16:00:00'),
        ]);

        $this->assertSame(SlaStatus::Breached, $sla->statusFor($enquiry));
    }

    public function test_a_responded_enquiry_ignores_the_response_due_date(): void
    {
        $clock = new FrozenClock('2026-03-02 11:00:00');
        $sla = new SlaCalculator($clock);

        $enquiry = new Enquiry([
            'responded_at' => CarbonImmutable::parse('2026-03-02 10:05:00'),
            'response_due_at' => CarbonImmutable::parse('2026-03-02 10:30:00'), // already past, but responded
            'resolution_due_at' => CarbonImmutable::parse('2026-03-02 18:00:00'),
        ]);

        $this->assertSame(SlaStatus::OnTrack, $sla->statusFor($enquiry));
    }
}
