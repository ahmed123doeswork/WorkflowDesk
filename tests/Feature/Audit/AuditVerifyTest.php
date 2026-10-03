<?php

namespace Tests\Feature\Audit;

use App\Enums\Role;
use App\Models\Enquiry;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditChain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditVerifyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_clean_chain_verifies_as_valid(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->role(Role::Admin)->create(['tenant_id' => $tenant->id]);
        $enquiry = Enquiry::factory()->for($tenant)->create();

        AuditChain::record('enquiry.created', $enquiry, ['after' => []]);
        AuditChain::record('enquiry.updated', $enquiry, ['after' => []]);
        AuditChain::record('enquiry.updated', $enquiry, ['after' => []]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/audit/verify')
            ->assertOk()
            ->assertJson(['valid' => true, 'checked' => 3, 'broken_at' => null]);
    }

    public function test_a_forged_entry_is_detected_and_reported(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->role(Role::Admin)->create(['tenant_id' => $tenant->id]);
        $enquiry = Enquiry::factory()->for($tenant)->create();

        $first = AuditChain::record('enquiry.created', $enquiry, ['after' => []]);
        AuditChain::record('enquiry.updated', $enquiry, ['after' => []]);

        // The trigger only blocks UPDATE/DELETE, so simulate a forged entry
        // the way an attacker actually could: a crafted INSERT with a hash
        // that doesn't match its own contents.
        $forgedId = DB::table('audit_logs')->insertGetId([
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'action' => 'enquiry.updated',
            'auditable_type' => $enquiry::class,
            'auditable_id' => $enquiry->id,
            'changes' => json_encode(['after' => ['subject' => 'forged']]),
            'previous_hash' => $first->hash,
            'hash' => 'forged-hash-that-does-not-match-contents',
            'created_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/audit/verify')
            ->assertOk()
            ->assertJson(['valid' => false, 'broken_at' => $forgedId]);
    }

    public function test_non_admin_cannot_verify_the_chain(): void
    {
        $tenant = Tenant::factory()->create();
        $viewer = User::factory()->role(Role::Viewer)->create(['tenant_id' => $tenant->id]);

        Sanctum::actingAs($viewer);

        $this->getJson('/api/audit/verify')->assertStatus(403);
    }
}
