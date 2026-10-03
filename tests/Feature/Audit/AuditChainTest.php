<?php

namespace Tests\Feature\Audit;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Enquiry;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditChain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditChainTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_entry_links_to_the_previous_entrys_hash(): void
    {
        $tenant = Tenant::factory()->create();
        $enquiry = Enquiry::factory()->for($tenant)->create();

        $first = AuditChain::record('enquiry.created', $enquiry, ['after' => []]);
        $second = AuditChain::record('enquiry.updated', $enquiry, ['after' => []]);
        $third = AuditChain::record('enquiry.updated', $enquiry, ['after' => []]);

        $this->assertNull($first->previous_hash);
        $this->assertSame($first->hash, $second->previous_hash);
        $this->assertSame($second->hash, $third->previous_hash);
    }

    public function test_chains_are_independent_per_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $enquiryA = Enquiry::factory()->for($tenantA)->create();
        $enquiryB = Enquiry::factory()->for($tenantB)->create();

        AuditChain::record('enquiry.created', $enquiryA, ['after' => []]);
        $firstB = AuditChain::record('enquiry.created', $enquiryB, ['after' => []]);

        $this->assertNull($firstB->previous_hash);
    }

    public function test_assigning_and_transitioning_an_enquiry_both_extend_the_chain(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->role(Role::Admin)->create(['tenant_id' => $tenant->id]);
        $enquiry = Enquiry::factory()->for($tenant)->create();

        Sanctum::actingAs($admin);

        $this->patchJson("/api/enquiries/{$enquiry->id}/assign", [
            'assigned_to' => $admin->id,
        ], ['If-Match' => $enquiry->etag()])->assertOk();

        $afterAssign = $enquiry->fresh();

        $this->patchJson("/api/enquiries/{$enquiry->id}/transition", [
            'status' => 'in_progress',
        ], ['If-Match' => $afterAssign->etag()])->assertOk();

        $actions = AuditLog::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->orderBy('id')
            ->pluck('action');

        $this->assertSame(['enquiry.assigned', 'enquiry.status_changed'], $actions->all());
    }
}
