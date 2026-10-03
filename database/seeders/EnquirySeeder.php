<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Enquiry;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class EnquirySeeder extends Seeder
{
    public function run(): void
    {
        Tenant::all()->each(function (Tenant $tenant) {
            $counsellor = $tenant->users()->where('role', Role::Counsellor)->first();

            Enquiry::factory()
                ->count(5)
                ->create([
                    'tenant_id' => $tenant->id,
                    'created_by' => $counsellor?->id,
                    'assigned_to' => $counsellor?->id,
                ]);
        });
    }
}
