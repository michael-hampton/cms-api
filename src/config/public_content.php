<?php

return [
    'enabled' => env('PUBLIC_CONTENT_V2_ENABLED', false),
    'preview_enabled' => env('PUBLIC_CONTENT_V2_PREVIEW_ENABLED', true),
    'shadow_enabled' => env('PUBLIC_CONTENT_V2_SHADOW_ENABLED', false),
    'site_ids' => [],
    'page_types' => ['content', 'article', 'landing-page'],

    'slug_patterns' => [
        'flat' => [
            'pattern' => '{slug}',
            'priority' => 100,
        ],
        'category_prefix' => [
            'pattern' => 'category/{slug}',
            'priority' => 90,
        ],
        'category_slug' => [
            'pattern' => '{category}/{slug}',
            'priority' => 80,
        ],
        'category_subcategory_slug' => [
            'pattern' => '{category}/{subcategory}/{slug}',
            'priority' => 70,
        ],
    ],

    'widget_definitions' => [
        \App\Services\PublicContent\Widgets\PaywallOverlayWidget::class,
        \App\Services\PublicContent\Widgets\MostPopularArticlesWidget::class,
    ],

    'widgets' => [
        'page-title' => ['page_types' => ['article', 'review']],
        'hero-block' => ['page_types' => ['article', 'landing-page', 'review']],
        'category-pills' => ['page_types' => ['article']],
        'tags' => ['page_types' => ['article']],
        'page-actions' => ['page_types' => ['article']],
        'trending' => ['page_types' => ['article', 'landing-page']],
        'deals' => ['page_types' => ['article', 'landing-page']],
        'review-summary' => ['page_types' => ['review']],
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
