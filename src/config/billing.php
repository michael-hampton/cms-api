<?php
return [
    'tax_engine' => env('TAX_ENGINE', 'stripe'),
    'default_currency' => env('DEFAULT_CURRENCY', 'gbp'),
    'tax_supported_countries' => ['GB', 'US', 'CA', 'DE', 'FR', 'IT', 'ES', 'NL', 'AU', 'NZ'],
    'tax_supported_states' => [
        'US' => ['CA', 'NY', 'TX', 'FL', 'WA', 'IL', 'PA', 'OH'],
        'CA' => ['ON', 'QC', 'BC', 'AB', 'NS'],
    ],
];
