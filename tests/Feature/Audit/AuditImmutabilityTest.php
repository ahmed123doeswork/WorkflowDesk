<?php

namespace Tests\Feature\Audit;

use App\Enums\Role;
use App\Models\Enquiry;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditChain;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class AuditImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_raw_sql_update_is_rejected_by_the_database_trigger(): void
    {
        $tenant = Tenant::factory()->create();
        $enquiry = Enquiry::factory()->for($tenant)->create();
        $entry = AuditChain::record('enquiry.created', $enquiry, ['after' => ['subject' => $enquiry->subject]]);

        $originalAction = $entry->action;

        try {
            DB::statement('UPDATE audit_logs SET action = ? WHERE id = ?', ['tampered', $entry->id]);
            $this->fail('Expected the database trigger to reject the raw SQL update.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('immutable', $e->getMessage());
        }

        $this->assertSame($originalAction, $entry->fresh()->action);
    }

    public function test_raw_sql_delete_is_rejected_by_the_database_trigger(): void
    {
        $tenant = Tenant::factory()->create();
        $enquiry = Enquiry::factory()->for($tenant)->create();
        $entry = AuditChain::record('enquiry.created', $enquiry, ['after' => ['subject' => $enquiry->subject]]);

        try {
            DB::statement('DELETE FROM audit_logs WHERE id = ?', [$entry->id]);
            $this->fail('Expected the database trigger to reject the raw SQL delete.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('immutable', $e->getMessage());
        }

        $this->assertDatabaseHas('audit_logs', ['id' => $entry->id]);
    }

    public function test_eloquent_update_and_delete_are_also_blocked(): void
    {
        $tenant = Tenant::factory()->create();
        $enquiry = Enquiry::factory()->for($tenant)->create();
        $entry = AuditChain::record('enquiry.created', $enquiry, ['after' => []]);

        $this->expectException(RuntimeException::class);

        $entry->update(['action' => 'tampered']);
    }

    public function test_user_deletion_writes_an_audit_entry_before_removing_the_row(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->role(Role::Admin)->create(['tenant_id' => $tenant->id]);
        $target = User::factory()->create(['tenant_id' => $tenant->id]);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/users/{$target->id}")->assertStatus(204);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.deleted',
            'auditable_id' => $target->id,
        ]);
    }
}
