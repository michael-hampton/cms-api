<?php

return [
    'enabled' => env('PUBLIC_CONTENT_V2_ENABLED', false),
    'preview_enabled' => env('PUBLIC_CONTENT_V2_PREVIEW_ENABLED', true),
    'shadow_enabled' => env('PUBLIC_CONTENT_V2_SHADOW_ENABLED', false),
    'site_ids' => [],
    'page_types' => ['content', 'article', 'landing-page'],

    'widget_definitions' => [
        \App\Services\PublicContent\Widgets\PaywallOverlayWidget::class,
        \App\Services\PublicContent\Widgets\MostPopularArticlesWidget::class,
    ],

    'widgets' => [
        'page-title' => ['page_types' => ['article']],
        'category-pills' => ['page_types' => ['article']],
        'tags' => ['page_types' => ['article']],
        'page-actions' => ['page_types' => ['article']],
        'trending' => ['page_types' => ['article', 'landing-page']],
        'deals' => ['page_types' => ['article', 'landing-page']],
        'vouchers' => [
            'page_types' => ['landing-page'],
            'limit' => 8,
        ],
        'adverts' => ['page_types' => ['article', 'landing-page']],
        'most-popular-articles' => [
            'page_types' => ['landing-page'],
            'limit' => 6,
        ],
        'comments' => ['page_types' => ['article']],
    ],

    'cache' => [
        'public_ttl_seconds' => 300,
        'viewer_state' => 'private, no-store',
    ],
];
