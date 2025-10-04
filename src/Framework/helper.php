<?php

use App\Framework\Authorization\Auth;
use App\Framework\Support\Collection;

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