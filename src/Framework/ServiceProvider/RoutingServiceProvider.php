<?php

namespace App\Framework\ServiceProvider;

use App\Framework\Http\Router;
use App\Framework\Routing\RouteLoader;

/**
 * Routing Service Provider
 * Handles router setup and route loading
 */
class RoutingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register Router as singleton
        $this->container->singleton(Router::class, function() {
            return new Router($this->container);
        });

        // Register RouteLoader
        $this->container->bind(RouteLoader::class, function() {
            return new RouteLoader($this->container->resolve(Router::class));
        });
    }

    public function boot(): void
    {
        die('here 8');

        // Auto-load route files if they exist
        $this->loadRouteFiles();
    }

    private function loadRouteFiles(): void
    {
        $routeLoader = $this->container->resolve(RouteLoader::class);

        $routeFiles = [
            'routes/web.php',
            'routes/api.php',
            // Add more route files as needed
            // 'routes/admin.php',
            // 'routes/console.php',
        ];

        foreach ($routeFiles as $routeFile) {
            if (file_exists($routeFile)) {
                $routeLoader->load($routeFile);
            }
        }
    }
}

// Update your config/app.php to include the routing provider:
/*
return [
    'providers' => [
        \App\Framework\ServiceProvider\CoreServiceProvider::class,
        \App\Framework\ServiceProvider\RepositoryServiceProvider::class,
        \App\Framework\ServiceProvider\ParserServiceProvider::class,
        \App\Framework\ServiceProvider\RoutingServiceProvider::class,  // Add this
        \App\Framework\ServiceProvider\ServiceServiceProvider::class,
        \App\Framework\ServiceProvider\ControllerServiceProvider::class,
    ],
    // ... rest of config
];
*/