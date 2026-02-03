<?php
return [
    'weights' => [
        'purchased' => 3.0,
        'viewed' => 1.0,
        'recent_multiplier' => 1.5,
    ],
    'limits' => [
        'related_per_product' => 3,
        'viewed_lookback_days' => 30,
        'max_order_history' => 20,
        'max_view_history' => 20,
        'frequently_viewed_limit' => 3,
    ],
    'defaults' => [
        'description_length' => 150,
        'recommendation_count' => 6,
    ],
    'currency' => [
        'default' => 'USD',
        'symbol_map' => [
            'USD' => '$',
            'GBP' => '£',
            'EUR' => '€',
        ],
    ],
];