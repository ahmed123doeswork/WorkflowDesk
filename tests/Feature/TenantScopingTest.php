<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_cross_tenant_user_lookup_returns_404(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $admin = User::factory()->role(Role::Admin)->create(['tenant_id' => $tenantA->id]);
        $otherTenantUser = User::factory()->create(['tenant_id' => $tenantB->id]);

        Sanctum::actingAs($admin);

        $this->getJson("/api/users/{$otherTenantUser->id}")
            ->assertNotFound();
    }

    public function test_user_index_only_lists_own_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $admin = User::factory()->role(Role::Admin)->create(['tenant_id' => $tenantA->id]);
        User::factory()->count(2)->create(['tenant_id' => $tenantA->id]);
        User::factory()->count(3)->create(['tenant_id' => $tenantB->id]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/users')->assertOk();

        $ids = collect($response->json('data'))->pluck('tenant_id')->unique();

        $this->assertEquals([$tenantA->id], $ids->all());
    }

    public function test_cross_tenant_update_returns_404(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $admin = User::factory()->role(Role::Admin)->create(['tenant_id' => $tenantA->id]);
        $otherTenantUser = User::factory()->create(['tenant_id' => $tenantB->id]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/users/{$otherTenantUser->id}", ['name' => 'Hacked'])
            ->assertNotFound();
    }
}
