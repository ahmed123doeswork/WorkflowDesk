<?php

namespace Tests\Feature\Enquiries;

use App\Enums\Role;
use App\Models\Enquiry;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OptimisticLockingTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_an_etag_header(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->role(Role::Admin)->create(['tenant_id' => $tenant->id]);
        $enquiry = Enquiry::factory()->for($tenant)->create();

        Sanctum::actingAs($admin);

        $this->getJson("/api/enquiries/{$enquiry->id}")
            ->assertOk()
            ->assertHeader('ETag', $enquiry->etag());
    }

    public function test_update_without_if_match_header_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->role(Role::Admin)->create(['tenant_id' => $tenant->id]);
        $enquiry = Enquiry::factory()->for($tenant)->create();

        Sanctum::actingAs($admin);

        $this->patchJson("/api/enquiries/{$enquiry->id}", ['subject' => 'New subject'])
            ->assertStatus(428);

        $this->assertSame($enquiry->subject, $enquiry->fresh()->subject);
    }

    public function test_stale_if_match_is_rejected_and_does_not_overwrite(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->role(Role::Admin)->create(['tenant_id' => $tenant->id]);
        $enquiry = Enquiry::factory()->for($tenant)->create(['subject' => 'Original subject']);

        $staleEtag = $enquiry->etag();

        Sanctum::actingAs($admin);

        // Someone else updates the enquiry first, advancing its version.
        $this->patchJson("/api/enquiries/{$enquiry->id}", ['subject' => 'Updated by someone else'], [
            'If-Match' => $staleEtag,
        ])->assertOk();

        // The original client retries its write with the now-stale ETag.
        $response = $this->patchJson("/api/enquiries/{$enquiry->id}", ['subject' => 'Overwritten?'], [
            'If-Match' => $staleEtag,
        ]);

        $response->assertStatus(412);
        $this->assertSame('Updated by someone else', $enquiry->fresh()->subject);
    }

    public function test_matching_if_match_succeeds_and_advances_version(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->role(Role::Admin)->create(['tenant_id' => $tenant->id]);
        $enquiry = Enquiry::factory()->for($tenant)->create();

        $originalVersion = $enquiry->version;

        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/enquiries/{$enquiry->id}", ['subject' => 'Updated subject'], [
            'If-Match' => $enquiry->etag(),
        ])->assertOk();

        $this->assertGreaterThan($originalVersion, $enquiry->fresh()->version);
        $this->assertNotSame($enquiry->etag(), $response->headers->get('ETag'));
    }
}
