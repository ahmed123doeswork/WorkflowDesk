<?php

namespace App\Console\Commands;

use App\Enums\EnquiryStatus;
use App\Models\Enquiry;
use App\Services\AuditChain;
use App\Services\SlaCalculator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('enquiries:check-sla')]
#[Description('Recompute SLA status for open enquiries and flag at-risk/breached ones')]
class CheckEnquirySlas extends Command
{
    public function handle(SlaCalculator $sla): int
    {
        $changed = 0;

        Enquiry::withoutGlobalScopes()
            ->whereNotIn('status', [EnquiryStatus::Resolved, EnquiryStatus::Closed])
            ->chunkById(100, function ($enquiries) use ($sla, &$changed) {
                foreach ($enquiries as $enquiry) {
                    $newStatus = $sla->statusFor($enquiry);

                    if ($newStatus === $enquiry->sla_status) {
                        continue;
                    }

                    DB::transaction(function () use ($enquiry, $newStatus) {
                        $oldStatus = $enquiry->sla_status;

                        $enquiry->update(['sla_status' => $newStatus]);

                        AuditChain::record('enquiry.sla_status_changed', $enquiry, [
                            'before' => ['sla_status' => $oldStatus?->value],
                            'after' => ['sla_status' => $newStatus->value],
                        ]);
                    });

                    $changed++;
                }
            });

        $this->info("Checked SLA status for open enquiries; {$changed} updated.");

        return self::SUCCESS;
    }
}
