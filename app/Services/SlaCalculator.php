<?php

namespace App\Services;

use App\Enums\Priority;
use App\Enums\SlaStatus;
use App\Models\Enquiry;
use App\Models\Tenant;
use App\Support\Clock;
use Carbon\CarbonImmutable;

class SlaCalculator
{
    public function __construct(private readonly Clock $clock) {}

    /**
     * @return array{response_due_at: CarbonImmutable, resolution_due_at: CarbonImmutable}
     */
    public function dueDates(Tenant $tenant, Priority $priority, ?CarbonImmutable $from = null): array
    {
        $from ??= $this->clock->now();
        $calendar = $tenant->businessCalendar();
        $target = config('sla.targets.'.$priority->value);

        return [
            'response_due_at' => $calendar->addBusinessMinutes($from, $target['response']),
            'resolution_due_at' => $calendar->addBusinessMinutes($from, $target['resolution']),
        ];
    }

    public function statusFor(Enquiry $enquiry): SlaStatus
    {
        $now = $this->clock->now();
        $bufferMinutes = config('sla.at_risk_buffer_minutes');

        $responseBreached = ! $enquiry->responded_at && $enquiry->response_due_at && $now->greaterThan($enquiry->response_due_at);
        $resolutionBreached = ! $enquiry->resolved_at && $enquiry->resolution_due_at && $now->greaterThan($enquiry->resolution_due_at);

        if ($responseBreached || $resolutionBreached) {
            return SlaStatus::Breached;
        }

        // Not breached, so any due date here is still in the future (or
        // null) - a plain absolute diff is safe without sign ambiguity.
        $responseAtRisk = ! $enquiry->responded_at && $enquiry->response_due_at
            && $now->diffInMinutes($enquiry->response_due_at) <= $bufferMinutes;

        $resolutionAtRisk = ! $enquiry->resolved_at && $enquiry->resolution_due_at
            && $now->diffInMinutes($enquiry->resolution_due_at) <= $bufferMinutes;

        if ($responseAtRisk || $resolutionAtRisk) {
            return SlaStatus::AtRisk;
        }

        return SlaStatus::OnTrack;
    }
}
