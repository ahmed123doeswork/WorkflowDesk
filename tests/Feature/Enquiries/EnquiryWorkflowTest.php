<?php

namespace Tests\Feature\Enquiries;

use App\Enums\EnquiryStatus;
use App\Enums\Priority;
use App\Enums\Role;
use App\Models\Enquiry;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EnquiryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_filters_by_status_and_priority(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->role(Role::Admin)->create(['tenant_id' => $tenant->id]);

        Enquiry::factory()->for($tenant)->status(EnquiryStatus::New)->priority(Priority::High)->create();
        Enquiry::factory()->for($tenant)->status(EnquiryStatus::Resolved)->priority(Priority::Low)->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/enquiries?status=new&priority=high')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('new', $response->json('data.0.status'));
    }

    public function test_viewer_cannot_create_enquiry(): void
    {
        $tenant = Tenant::factory()->create();
        $viewer = User::factory()->role(Role::Viewer)->create(['tenant_id' => $tenant->id]);

        Sanctum::actingAs($viewer);

        $this->postJson('/api/enquiries', [
            'student_name' => 'Jane Student',
            'student_email' => 'jane@example.com',
            'subject' => 'Help',
            'description' => 'Need help enrolling.',
        ])->assertStatus(403);
    }

    public function test_counsellor_can_create_and_assign_enquiry(): void
    {
        $tenant = Tenant::factory()->create();
        $counsellor = User::factory()->role(Role::Counsellor)->create(['tenant_id' => $tenant->id]);

        Sanctum::actingAs($counsellor);

        $created = $this->postJson('/api/enquiries', [
            'student_name' => 'Jane Student',
            'student_email' => 'jane@example.com',
            'subject' => 'Help',
            'description' => 'Need help enrolling.',
        ])->assertStatus(201);

        $enquiry = Enquiry::find($created->json('id'));
        $etag = $created->headers->get('ETag');

        $this->patchJson("/api/enquiries/{$enquiry->id}/assign", [
            'assigned_to' => $counsellor->id,
        ], ['If-Match' => $etag])
            ->assertOk()
            ->assertJsonPath('assigned_to', $counsellor->id);
    }

    public function test_valid_transition_succeeds(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->role(Role::Admin)->create(['tenant_id' => $tenant->id]);
        $enquiry = Enquiry::factory()->for($tenant)->status(EnquiryStatus::New)->create();

        Sanctum::actingAs($admin);

        $this->patchJson("/api/enquiries/{$enquiry->id}/transition", [
            'status' => 'in_progress',
        ], ['If-Match' => $enquiry->etag()])
            ->assertOk()
            ->assertJsonPath('status', 'in_progress');
    }

    public function test_invalid_transition_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->role(Role::Admin)->create(['tenant_id' => $tenant->id]);
        $enquiry = Enquiry::factory()->for($tenant)->status(EnquiryStatus::New)->create();

        Sanctum::actingAs($admin);

        $this->patchJson("/api/enquiries/{$enquiry->id}/transition", [
            'status' => 'resolved',
        ], ['If-Match' => $enquiry->etag()])
            ->assertStatus(422);
    }

    public function test_viewer_cannot_transition_enquiry(): void
    {
        $tenant = Tenant::factory()->create();
        $viewer = User::factory()->role(Role::Viewer)->create(['tenant_id' => $tenant->id]);
        $enquiry = Enquiry::factory()->for($tenant)->status(EnquiryStatus::New)->create();

        Sanctum::actingAs($viewer);

        $this->patchJson("/api/enquiries/{$enquiry->id}/transition", [
            'status' => 'in_progress',
        ], ['If-Match' => $enquiry->etag()])
            ->assertStatus(403);
    }

    public function test_cross_tenant_enquiry_access_returns_404(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $admin = User::factory()->role(Role::Admin)->create(['tenant_id' => $tenantA->id]);
        $otherEnquiry = Enquiry::factory()->for($tenantB)->create();

        Sanctum::actingAs($admin);

        $this->getJson("/api/enquiries/{$otherEnquiry->id}")->assertNotFound();
    }
}
