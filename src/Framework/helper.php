<?php

use App\Framework\Authorization\Auth;
use App\Framework\Container;
use App\Framework\Support\Collection;
use App\Framework\Support\SiteContext;

if (!function_exists('auth')) {
    function auth(): Auth
    {
        return new Auth();
    }
}

if (!function_exists('collect')) {
    function collect($items = []): Collection
    {
        return new Collection($items);
    }
}

if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$headerName] = $value;
            }
        }
        return $headers;
    }
}

if (!function_exists('app')) {
    /**
     * Resolve an instance from the container.
     *
     * @template T
     * @param class-string<T>|null $abstract
     * @param array $parameters
     * @return ($abstract is null ? Container : T)
     */
    function app(?string $abstract = null, array $parameters = []): mixed
    {
        $container = Container::getInstance();

        if ($abstract === null) {
            return $container;
        }

        return $container->resolve($abstract, $parameters);
    }
}

// Add this to a helper file or bootstrap.php

if (!function_exists('config')) {
    /**
     * Get configuration value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function config(string $key, $default = null)
    {
        static $config = null;

        if ($config === null) {
            // Load all config files
            $config = [];

            $configFiles = [
                'app' => 'config/app.php',
                'database' => 'config/database.php',
                'routing' => 'config/routing.php',
            ];

            foreach ($configFiles as $name => $file) {
                if (file_exists($file)) {
                    $config[$name] = require $file;
                }
            }
        }

        // Support dot notation: 'app.debug' or 'database.host'
        $keys = explode('.', $key);
        $value = $config;

        foreach ($keys as $segment) {
            if (!isset($value[$segment])) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}

if (!function_exists('env')) {
    /**
     * Get environment variable
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function env(string $key, $default = null)
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        // Convert common string values to proper types
        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'null':
            case '(null)':
                return null;
            case 'empty':
            case '(empty)':
                return '';
        }

        return $value;
    }
}

if (!function_exists('site')) {
    function site(): ?\App\Models\Site
    {
        return SiteContext::get();
    }
}

if(!function_exists('asset')) {
    function asset(string $path, string $type = 'css'): string
    {
        // Determine folder based on type
        $folder = match ($type) {
            'css' => 'css',
            'js' => 'js',
            default => '',
        };

        // Build URL
        $url = '/public/' . ($folder ? $folder . '/' : '') . ltrim($path, '/');

        // Build filesystem path for cache-busting
        $file = __DIR__ . '/../public/' . ($folder ? $folder . '/' : '') . ltrim($path, '/');


        // Add cache-busting if file exists
        if (file_exists($file)) {
            $url .= '?v=' . filemtime($file);
        }

        return $url;
    }
}

if (!function_exists('site_id')) {
    function site_id(): ?int
    {
        return SiteContext::getId();
    }
}

if (!function_exists('site_url')) {
    function site_url(string $path = ''): string
    {
        return SiteContext::url($path);
    }
}

if (!function_exists('site_asset')) {
    function site_asset(string $path): string
    {
        return SiteContext::asset($path);
    }
}

if (!function_exists('site_css')) {
    function site_css(): string
    {
        return SiteContext::css();
    }
}