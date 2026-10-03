<?php

return [
    /*
     * Response and resolution targets, in business minutes, by priority.
     * Business minutes only tick during the tenant's working hours
     * (Mon-Fri, 09:00-17:00 local), skipping weekends and holidays.
     */
    'targets' => [
        'urgent' => ['response' => 30, 'resolution' => 240],
        'high' => ['response' => 60, 'resolution' => 480],
        'medium' => ['response' => 240, 'resolution' => 960],
        'low' => ['response' => 480, 'resolution' => 2400],
    ],

    /*
     * An enquiry is "at risk" once it's within this many wall-clock minutes
     * of a due date and hasn't breached it yet.
     */
    'at_risk_buffer_minutes' => 60,
];
