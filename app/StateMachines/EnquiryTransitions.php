<?php

namespace App\StateMachines;

use App\Enums\EnquiryStatus;

class EnquiryTransitions
{
    /**
     * @var array<string, list<string>>
     */
    private const MAP = [
        EnquiryStatus::New->value => [EnquiryStatus::InProgress->value],
        EnquiryStatus::InProgress->value => [EnquiryStatus::Waiting->value, EnquiryStatus::Resolved->value],
        EnquiryStatus::Waiting->value => [EnquiryStatus::InProgress->value],
        EnquiryStatus::Resolved->value => [EnquiryStatus::Closed->value, EnquiryStatus::InProgress->value],
        EnquiryStatus::Closed->value => [],
    ];

    public static function canTransition(EnquiryStatus $from, EnquiryStatus $to): bool
    {
        return in_array($to->value, self::MAP[$from->value], true);
    }

    /**
     * @return list<EnquiryStatus>
     */
    public static function allowedFrom(EnquiryStatus $from): array
    {
        return array_map(
            fn (string $value) => EnquiryStatus::from($value),
            self::MAP[$from->value],
        );
    }
}
