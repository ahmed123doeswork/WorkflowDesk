<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * role => [can view, can update, can delete (someone else)]
     */
    public static function matrix(): array
    {
        return [
            'admin' => [Role::Admin, true, true, true],
            'counsellor' => [Role::Counsellor, true, false, false],
            'viewer' => [Role::Viewer, true, false, false],
        ];
    }

    #[DataProvider('matrix')]
    public function test_role_permission_matrix(Role $role, bool $canView, bool $canUpdate, bool $canDelete): void
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->role($role)->create(['tenant_id' => $tenant->id]);
        $target = User::factory()->create(['tenant_id' => $tenant->id]);

        Sanctum::actingAs($actor);

        $this->getJson("/api/users/{$target->id}")
            ->assertStatus($canView ? 200 : 403);

        $this->patchJson("/api/users/{$target->id}", ['name' => 'Updated'])
            ->assertStatus($canUpdate ? 200 : 403);

        $this->deleteJson("/api/users/{$target->id}")
            ->assertStatus($canDelete ? 204 : 403);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->role(Role::Admin)->create(['tenant_id' => $tenant->id]);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/users/{$admin->id}")
            ->assertStatus(403);
    }
}
