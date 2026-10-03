<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::all()->each(function (Tenant $tenant) {
            $slug = $tenant->slug;

            foreach ([Role::Admin, Role::Counsellor, Role::Viewer] as $role) {
                User::factory()->role($role)->create([
                    'tenant_id' => $tenant->id,
                    'name' => ucfirst($role->value).' ('.$tenant->name.')',
                    'email' => "{$role->value}@{$slug}.test",
                ]);
            }
        });
    }
}
