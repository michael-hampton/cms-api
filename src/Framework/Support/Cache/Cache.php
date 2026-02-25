<?php

namespace App\Framework\Support\Cache;

class Cache
{
    private static $cache = [];

    public static function put(string $key, $value, int $seconds = 3600): void
    {
        self::$cache[$key] = [
            'value' => $value,
            'expires' => time() + $seconds
        ];
    }

    public static function get(string $key, $default = null)
    {
        if (!isset(self::$cache[$key])) {
            return $default;
        }

        $item = self::$cache[$key];

        if (time() > $item['expires']) {
            unset(self::$cache[$key]);
            return $default;
        }

        return $item['value'];
    }

    public static function forget(string $key): void
    {
        unset(self::$cache[$key]);
    }

    public static function flush(): void
    {
        self::$cache = [];
    }

    public static function remember(string $key, int $seconds, callable $callback)
    {
        $value = self::get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        self::put($key, $value, $seconds);

        return $value;
    }

    public static function add(string $key, $value, int $seconds): bool
    {
        // If key exists and not expired → fail
        if (isset(self::$cache[$key])) {
            $item = self::$cache[$key];

            if (time() <= $item['expires']) {
                return false;
            }
        }

        self::$cache[$key] = [
            'value' => $value,
            'expires' => time() + $seconds
        ];

        return true;
    }
}