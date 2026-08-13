<?php

return [
    'enabled' => env('PUBLIC_CONTENT_V2_ENABLED', false),
    'preview_enabled' => env('PUBLIC_CONTENT_V2_PREVIEW_ENABLED', true),
    'shadow_enabled' => env('PUBLIC_CONTENT_V2_SHADOW_ENABLED', false),
    'site_ids' => [],
    'page_types' => ['content', 'article', 'landing-page', 'review', 'buying-guide'],

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
        'adverts' => [
            'page_types' => ['article', 'landing-page'],
            // User-facing: less | balanced | more (see AdvertFrequency).
            'frequency' => 'balanced',
        ],
        'most-popular-articles' => [
            'page_types' => ['landing-page'],
            'limit' => 6,
        ],
        'comments' => ['page_types' => ['article']],
        'recirculation' => ['page_types' => ['article', 'review', 'buying-guide']],
        'social-links' => [
            'page_types' => ['article', 'review', 'buying-guide'],
            'region' => 'header',
            'priority' => 35,
        ],
    ],

    'cache' => [
        'public_ttl_seconds' => 300,
        'viewer_state' => 'private, no-store',
        // Bound within which Pods responses / edge state must clear after a kill-switch.
        'kill_switch_cache_clear_seconds' => (int) env('PUBLIC_CONTENT_V2_KILL_CACHE_CLEAR_SECONDS', 60),
    ],

    'runtime' => [
        // Named live-traffic failure signal. Threshold breach triggers the same
        // kill path as a parity regression.
        'failure_rate_threshold' => (float) env('PUBLIC_CONTENT_V2_FAILURE_RATE_THRESHOLD', 0.05),
        'failure_window_size' => (int) env('PUBLIC_CONTENT_V2_FAILURE_WINDOW_SIZE', 100),
        'signal' => 'public_content.runtime_failure_rate',
        // Composition deadline for GetPublicContent (ms). Slow islands must
        // degrade inside this window rather than widening it.
        'timeout_milliseconds' => (int) env('PUBLIC_CONTENT_V2_TIMEOUT_MS', 1500),
        // Minimum remaining budget required before starting recirculation.
        'recirculation_budget_milliseconds' => (int) env('PUBLIC_CONTENT_V2_RECIRCULATION_BUDGET_MS', 300),
    ],

    'locale_rules' => [
        // Versioned artefact path relative to src/. Missing or malformed refuses start-up.
        'path' => env('PUBLIC_CONTENT_LOCALE_RULES_PATH', 'config/public-content-locale-rules.json'),
    ],

    'design_tokens_artefact' => [
        'path' => env('PUBLIC_CONTENT_DESIGN_TOKENS_ARTEFACT_PATH', 'config/public-content-design-tokens.json'),
    ],

    'allowed_regions_artefact' => [
        'path' => env('PUBLIC_CONTENT_ALLOWED_REGIONS_ARTEFACT_PATH', 'config/public-content-allowed-regions.json'),
    ],

    'layout' => [
        // Site catch-all template used when page_settings.template is unset.
        // Empty / absent means NoLayoutResolved (page_type is never a silent fallback).
        'default_template' => env('PUBLIC_CONTENT_DEFAULT_TEMPLATE', ''),
    ],

    'images' => [
        // Hosts (or parent domains, matched by suffix) the CDN-style image
        // transform library is allowed to rewrite. Comma-separated list.
        // Empty by default: unrecognised hosts always fail open (original
        // URL, unchanged) via App\Services\PublicContent\Images\Transform.
        // Default quality (80) when a transform is written without an
        // explicit quality is a documented constant on the builders
        // themselves (SimpleImageUrlBuilder::DEFAULT_QUALITY /
        // RichImageUrlBuilder::DEFAULT_QUALITY), not configuration.
        'recognised_hosts' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('PUBLIC_CONTENT_IMAGE_RECOGNISED_HOSTS', '')),
        ))),
        // Optional absolute http(s) base checked when the transform library
        // loads. Empty means unused. A malformed value refuses to boot.
        'base_url' => (string) env('PUBLIC_CONTENT_IMAGE_BASE_URL', ''),
    ],
];
