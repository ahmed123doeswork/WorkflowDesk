<?php

namespace Tests\Unit;

use App\Enums\EnquiryStatus;
use App\StateMachines\EnquiryTransitions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EnquiryTransitionsTest extends TestCase
{
    public static function allowedTransitions(): array
    {
        return [
            'new to in_progress' => [EnquiryStatus::New, EnquiryStatus::InProgress, true],
            'new to resolved (skip)' => [EnquiryStatus::New, EnquiryStatus::Resolved, false],
            'in_progress to waiting' => [EnquiryStatus::InProgress, EnquiryStatus::Waiting, true],
            'in_progress to resolved' => [EnquiryStatus::InProgress, EnquiryStatus::Resolved, true],
            'in_progress to closed (skip)' => [EnquiryStatus::InProgress, EnquiryStatus::Closed, false],
            'waiting to in_progress' => [EnquiryStatus::Waiting, EnquiryStatus::InProgress, true],
            'waiting to resolved (skip)' => [EnquiryStatus::Waiting, EnquiryStatus::Resolved, false],
            'resolved to closed' => [EnquiryStatus::Resolved, EnquiryStatus::Closed, true],
            'resolved to in_progress (reopen)' => [EnquiryStatus::Resolved, EnquiryStatus::InProgress, true],
            'closed to anything' => [EnquiryStatus::Closed, EnquiryStatus::InProgress, false],
        ];
    }

    #[DataProvider('allowedTransitions')]
    public function test_transition_matrix(EnquiryStatus $from, EnquiryStatus $to, bool $expected): void
    {
        $this->assertSame($expected, EnquiryTransitions::canTransition($from, $to));
    }

    public function test_closed_has_no_allowed_transitions(): void
    {
        $this->assertSame([], EnquiryTransitions::allowedFrom(EnquiryStatus::Closed));
    }
}
