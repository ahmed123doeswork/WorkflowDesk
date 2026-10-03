<?php

namespace Tests\Feature;

use App\Enums\EnquiryStatus;
use App\Enums\SlaStatus;
use App\Models\AuditLog;
use App\Models\Enquiry;
use App\Models\Tenant;
use App\Support\Clock;
use App\Support\FrozenClock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckEnquirySlasCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_flags_an_overdue_enquiry_as_breached_and_writes_an_audit_entry(): void
    {
        $tenant = Tenant::factory()->create(['timezone' => 'UTC']);

        $enquiry = Enquiry::factory()->for($tenant)->status(EnquiryStatus::New)->create([
            'response_due_at' => CarbonImmutable::parse('2026-03-02 10:30:00'),
            'resolution_due_at' => CarbonImmutable::parse('2026-03-02 14:00:00'),
        ]);

        $this->app->instance(Clock::class, new FrozenClock('2026-03-02 15:00:00'));

        $this->artisan('enquiries:check-sla')->assertExitCode(0);

        $this->assertSame(SlaStatus::Breached, $enquiry->fresh()->sla_status);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'enquiry.sla_status_changed',
            'auditable_id' => $enquiry->id,
        ]);
    }

    public function test_it_flags_an_approaching_due_date_as_at_risk(): void
    {
        $tenant = Tenant::factory()->create(['timezone' => 'UTC']);

        $enquiry = Enquiry::factory()->for($tenant)->status(EnquiryStatus::New)->create([
            'response_due_at' => CarbonImmutable::parse('2026-03-02 10:30:00'),
            'resolution_due_at' => CarbonImmutable::parse('2026-03-02 14:00:00'),
        ]);

        // 30 minutes before the response due date, inside the default
        // 60-minute at-risk buffer.
        $this->app->instance(Clock::class, new FrozenClock('2026-03-02 10:00:00'));

        $this->artisan('enquiries:check-sla')->assertExitCode(0);

        $this->assertSame(SlaStatus::AtRisk, $enquiry->fresh()->sla_status);
    }

    public function test_it_leaves_on_track_enquiries_untouched(): void
    {
        $tenant = Tenant::factory()->create(['timezone' => 'UTC']);

        $enquiry = Enquiry::factory()->for($tenant)->status(EnquiryStatus::New)->create([
            'response_due_at' => CarbonImmutable::parse('2026-03-02 16:30:00'),
            'resolution_due_at' => CarbonImmutable::parse('2026-03-02 20:00:00'),
        ]);

        $this->app->instance(Clock::class, new FrozenClock('2026-03-02 09:00:00'));

        $this->artisan('enquiries:check-sla')->assertExitCode(0);

        $this->assertSame(SlaStatus::OnTrack, $enquiry->fresh()->sla_status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_it_ignores_resolved_and_closed_enquiries(): void
    {
        $tenant = Tenant::factory()->create(['timezone' => 'UTC']);

        $resolved = Enquiry::factory()->for($tenant)->status(EnquiryStatus::Resolved)->create([
            'resolved_at' => CarbonImmutable::parse('2026-03-01 10:00:00'),
            'resolution_due_at' => CarbonImmutable::parse('2026-03-02 14:00:00'),
        ]);

        $this->app->instance(Clock::class, new FrozenClock('2026-03-05 10:00:00'));

        $this->artisan('enquiries:check-sla')->assertExitCode(0);

        $this->assertSame(SlaStatus::OnTrack, $resolved->fresh()->sla_status);
    }
}
