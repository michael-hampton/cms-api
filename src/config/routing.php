<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Route Files
    |--------------------------------------------------------------------------
    |
    | List of route files to load automatically. Files are loaded in order.
    |
    */
    'files' => [
        'routes/web.php',
        'routes/api.php',
        // 'routes/admin.php',
        // 'routes/console.php',
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Groups
    |--------------------------------------------------------------------------
    |
    | Define route groups with shared attributes like middleware, prefix, etc.
    |
    */
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

    /*
    |--------------------------------------------------------------------------
    | Route Model Bindings
    |--------------------------------------------------------------------------
    |
    | Define model bindings for route parameters
    |
    */
    'bindings' => [
        'user' => \App\Framework\AuthenticatedUser::class,
        'page' => \App\Models\Page::class,
        'category' => \App\Models\Category::class,
        'tag' => \App\Models\Tag::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Caching
    |--------------------------------------------------------------------------
    |
    | Enable route caching for better performance in production
    |
    */
    'cache' => [
        'enabled' => env('ROUTE_CACHE_ENABLED', false),
        'path' => 'storage/cache/routes.php',
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware Groups
    |--------------------------------------------------------------------------
    |
    | Define middleware groups for easier assignment
    |
    */
    'middleware_groups' => [
        'web' => [
            'csrf',
            'session',
        ],

        'api' => [
            'throttle:60,1',
            'cors',
        ],

        'admin' => [
            'auth',
            'admin_role',
            'csrf',
        ],
    ],
];