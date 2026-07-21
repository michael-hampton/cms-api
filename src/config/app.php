<?php

// config/app.php
return [
    'debug' => true,
    'url' => env('APP_URL', 'http://localhost'),
    'default_site_id' => 1,
    'name' => env('APP_NAME', 'CMS Application'),
    'providers' => [
        \App\Framework\ServiceProvider\AuthServiceProvider::class,
        \App\Framework\ServiceProvider\CoreServiceProvider::class,
        \App\Framework\ServiceProvider\RepositoryServiceProvider::class,
        \App\Framework\ServiceProvider\ParserServiceProvider::class,
        \App\Framework\ServiceProvider\ServiceServiceProvider::class,
        \App\Framework\ServiceProvider\ControllerServiceProvider::class,
        \App\Framework\ServiceProvider\ScheduleServiceProvider::class,
        \App\Framework\ServiceProvider\EventServiceProvider::class,
        \App\Providers\PublicContentConfigServiceProvider::class,
        \App\Providers\PublicContentWidgetServiceProvider::class,

        // Add custom providers here.
        // \App\Providers\CustomServiceProvider::class,
    ],

    'auto_discovery' => [
        'enabled' => true,
        'directories' => [
            'models' => 'Models',
            'repositories' => 'Repositories',
            'services' => 'Services',
            'controllers' => 'Controllers',
            'parsers' => 'Parsers',
        ],
    ],

    'singletons' => [
        // Explicit singleton bindings that can't be auto-discovered
        \App\Framework\Database\Database::class,
        \App\Framework\Events\EventDispatcher::class,
        \App\Framework\View\SimpleTemplateEngine::class,
        \App\Framework\View\ViewRenderer::class,
        \App\Framework\Validation\Validator::class,
        \App\Parsers\BlockRegistry::class,
    ],

    'bindings' => [
        \App\Repositories\PublicContent\Contracts\PageWidgetRepositoryInterface::class
            => \App\Repositories\PublicContent\PageWidgetRepository::class,
    ],
];
