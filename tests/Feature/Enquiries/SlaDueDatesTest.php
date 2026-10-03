<?php

namespace Tests\Feature\Enquiries;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Clock;
use App\Support\FrozenClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SlaDueDatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_an_enquiry_sets_due_dates_from_the_tenant_calendar(): void
    {
        $this->app->instance(Clock::class, new FrozenClock('2026-03-02 10:00:00'));

        $tenant = Tenant::factory()->create(['timezone' => 'UTC']);
        $counsellor = User::factory()->role(Role::Counsellor)->create(['tenant_id' => $tenant->id]);

        Sanctum::actingAs($counsellor);

        $response = $this->postJson('/api/enquiries', [
            'student_name' => 'Jane Student',
            'student_email' => 'jane@example.com',
            'subject' => 'Help',
            'description' => 'Need help enrolling.',
            'priority' => 'urgent',
        ])->assertStatus(201);

        // urgent: 30 min response, 240 min resolution, from 10:00 UTC Monday.
        $response->assertJsonPath('response_due_at', '2026-03-02T10:30:00.000000Z');
        $response->assertJsonPath('resolution_due_at', '2026-03-02T14:00:00.000000Z');
    }

    public function test_different_tenant_timezones_produce_different_utc_due_dates(): void
    {
        // 13:00 UTC is mid-afternoon in UTC (still business hours) but
        // 18:00 in Asia/Karachi (UTC+5, after hours) - so the Karachi
        // tenant's due date rolls to the next business day while the UTC
        // tenant's doesn't.
        $this->app->instance(Clock::class, new FrozenClock('2026-03-02 13:00:00'));

        $utcTenant = Tenant::factory()->create(['timezone' => 'UTC']);
        $karachiTenant = Tenant::factory()->create(['timezone' => 'Asia/Karachi']); // UTC+5

        $utcCounsellor = User::factory()->role(Role::Counsellor)->create(['tenant_id' => $utcTenant->id]);
        $karachiCounsellor = User::factory()->role(Role::Counsellor)->create(['tenant_id' => $karachiTenant->id]);

        Sanctum::actingAs($utcCounsellor);
        $utcResponse = $this->postJson('/api/enquiries', $this->payload('urgent'))->assertStatus(201);

        Sanctum::actingAs($karachiCounsellor);
        $karachiResponse = $this->postJson('/api/enquiries', $this->payload('urgent'))->assertStatus(201);

        $this->assertNotSame(
            $utcResponse->json('response_due_at'),
            $karachiResponse->json('response_due_at'),
        );
    }

    private function payload(string $priority): array
    {
        return [
            'student_name' => 'Jane Student',
            'student_email' => 'jane@example.com',
            'subject' => 'Help',
            'description' => 'Need help enrolling.',
            'priority' => $priority,
        ];
    }
}
