<?php

return [
    'enabled' => env('PUBLIC_CONTENT_V2_ENABLED', false),
    'preview_enabled' => env('PUBLIC_CONTENT_V2_PREVIEW_ENABLED', true),
    'shadow_enabled' => env('PUBLIC_CONTENT_V2_SHADOW_ENABLED', false),
    'site_ids' => [],
    'page_types' => ['content', 'article', 'landing-page'],
    'widgets' => [
        \App\Services\PublicContent\Widgets\PaywallOverlayWidget::class,
    ],
    'cache' => [
        'public_ttl_seconds' => 300,
        'viewer_state' => 'private, no-store',
    ],
    'media' => [
        'signed_ttl_seconds' => env('PUBLIC_MEDIA_SIGNED_TTL_SECONDS', 86400),
        'document_root' => env('PUBLIC_MEDIA_DOCUMENT_ROOT', null),
    ],
];
