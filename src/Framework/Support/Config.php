<?php

namespace App\Framework\Support;

class Config
{
    private static $config = [];

    public static function load(array $config = []): void
    {
        // Load environment first
        Env::load();

        // Merge with provided config
        self::$config = array_merge(self::$config, $config);
    }

    public static function get(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    public static function set(string $key, $value): void
    {
        $keys = explode('.', $key);
        $config = &self::$config;

        foreach ($keys as $k) {
            if (!isset($config[$k]) || !is_array($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }

        $config = $value;
    }

    public static function all(): array
    {
        return self::$config;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::$config);
    }
}