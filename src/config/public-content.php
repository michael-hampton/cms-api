<?php

return [
    'enabled' => env('PUBLIC_CONTENT_V2_ENABLED', false),
    'preview_enabled' => env('PUBLIC_CONTENT_V2_PREVIEW_ENABLED', true),
    'shadow_enabled' => env('PUBLIC_CONTENT_V2_SHADOW_ENABLED', false),
    'site_ids' => [],
    'page_types' => ['page', 'content', 'article', 'landing-page'],
    'widgets' => [],
    'cache' => [
        'public_ttl_seconds' => 300,
        'viewer_state' => 'private, no-store',
    ],
];
