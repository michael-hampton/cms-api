<?php

namespace App\Framework\Support\Cache;

class FileCache
{
    private static string $path = __DIR__ . '/../../../storage/cache';

    public static function put(string $key, $value, int $seconds = 3600): void
    {
        self::ensureDirectory();

        $payload = [
            'expires' => time() + $seconds,
            'value' => $value,
        ];

        file_put_contents(self::filePath($key), serialize($payload), LOCK_EX);
    }

    public static function get(string $key, $default = null)
    {
        $file = self::filePath($key);

        if (!file_exists($file)) {
            return $default;
        }

        $payload = unserialize(file_get_contents($file));

        if (time() > $payload['expires']) {
            unlink($file);
            return $default;
        }

        return $payload['value'];
    }

    public static function forget(string $key): void
    {
        $file = self::filePath($key);

        if (file_exists($file)) {
            unlink($file);
        }
    }

    public static function flush(): void
    {
        foreach (glob(self::$path . '/*.cache') as $file) {
            unlink($file);
        }
    }

    private static function filePath(string $key): string
    {
        return self::$path . '/' . sha1($key) . '.cache';
    }

    private static function ensureDirectory(): void
    {
        if (!is_dir(self::$path)) {
            mkdir(self::$path, 0755, true);
        }
    }
}
