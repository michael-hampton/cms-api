<?php

return [
    'files' => [
        'routes/public-content-preview.php',
        'routes/public-directory.php',
        'routes/web.php',
        'routes/api.php',
        'routes/public-content-api.php',
    ],

    'groups' => [
        'api' => [
            'prefix' => 'api',
            'middleware' => ['throttle', 'cors'],
        ],
        'admin' => [
            'prefix' => 'admin',
            'middleware' => ['auth', 'admin'],
        ],
        'auth' => [
            'middleware' => ['auth'],
        ],
    ],

    'bindings' => [
        'user' => \App\Framework\AuthenticatedUser::class,
        'page' => \App\Models\Page::class,
        'category' => \App\Models\Category::class,
        'tag' => \App\Models\Tag::class,
    ],

    'cache' => [
        'enabled' => env('ROUTE_CACHE_ENABLED', false),
        'path' => 'storage/cache/routes.php',
    ],

    'middleware_groups' => [
        'web' => ['csrf', 'session'],
        'api' => ['throttle:60,1', 'cors'],
        'admin' => ['auth', 'admin_role', 'csrf'],
    ],
];
