<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::factory()->create([
            'name' => 'Acme Education',
            'slug' => 'acme-education',
            'timezone' => 'America/New_York',
        ]);

        Tenant::factory()->create([
            'name' => 'Globex Learning',
            'slug' => 'globex-learning',
            'timezone' => 'Asia/Karachi',
        ]);
    }
}
