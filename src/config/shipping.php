<?php

return [
    'uk_bank_holidays' => [
        // 2025
        '2025-01-01', // New Year's Day
        '2025-04-18', // Good Friday
        '2025-04-21', // Easter Monday
        '2025-05-05', // Early May Bank Holiday
        '2025-05-26', // Spring Bank Holiday
        '2025-08-25', // Summer Bank Holiday
        '2025-12-25', // Christmas Day
        '2025-12-26', // Boxing Day

        // 2026
        '2026-01-01', // New Year's Day
        '2026-04-03', // Good Friday
        '2026-04-06', // Easter Monday
        '2026-05-04', // Early May Bank Holiday
        '2026-05-25', // Spring Bank Holiday
        '2026-08-31', // Summer Bank Holiday
        '2026-12-25', // Christmas Day
        '2026-12-28', // Boxing Day (substitute)
    ],

    'default_cutoff_time' => '14:00',
    'default_dispatch_days' => 2,
    'default_transit_min_days' => 2,
    'default_transit_max_days' => 5,
];