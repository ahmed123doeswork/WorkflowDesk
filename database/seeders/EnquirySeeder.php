<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Enquiry;
use App\Models\Tenant;
use App\Services\SlaCalculator;
use App\Support\SystemClock;
use Illuminate\Database\Seeder;

class EnquirySeeder extends Seeder
{
    public function run(): void
    {
        $sla = new SlaCalculator(new SystemClock);

        Tenant::all()->each(function (Tenant $tenant) use ($sla) {
            $counsellor = $tenant->users()->where('role', Role::Counsellor)->first();

            Enquiry::factory()
                ->count(5)
                ->make([
                    'tenant_id' => $tenant->id,
                    'created_by' => $counsellor?->id,
                    'assigned_to' => $counsellor?->id,
                ])
                ->each(function (Enquiry $enquiry) use ($sla, $tenant) {
                    $dueDates = $sla->dueDates($tenant, $enquiry->priority);
                    $enquiry->fill($dueDates)->save();
                });
        });
    }
}
