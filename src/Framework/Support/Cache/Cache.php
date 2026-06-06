<?php

namespace App\Framework\Support\Cache;

use App\Framework\Container;
use App\Framework\Support\Cache\Contracts\CacheInterface;

class Cache
{
    private static $cache = [];

    public static function put(
        string $key,
               $value,
        int|\DateTimeInterface $ttl = 3600
    ): void {
        $store = self::store();

        if ($store) {
            $store->put($key, $value, self::resolveTtl($ttl));
            return;
        }

        self::putInMemory($key, $value, self::resolveTtl($ttl));
    }

    private static function resolveTtl(
        int|\DateTimeInterface $ttl
    ): int {
        if (is_int($ttl)) {
            return $ttl;
        }

        return max(
            0,
            $ttl->getTimestamp() - time()
        );
    }

    public static function get(string $key, $default = null)
    {
        $store = self::store();

        if ($store) {
            return $store->get($key) ?? $default;
        }

        return self::getFromMemory($key, $default);
    }

    public static function forget(string $key): void
    {
        $store = self::store();

        if ($store) {
            $store->forget($key);
            return;
        }

        unset(self::$cache[$key]);
    }

    public static function forgetMany(array $keys): void
    {
        $store = self::store();

        if ($store) {
            $store->forgetMany($keys);
            return;
        }

        foreach ($keys as $key) {
            unset(self::$cache[$key]);
        }
    }

    public static function flush(): void
    {
        self::$cache = [];

        self::store()?->flush();
    }

    public static function remember(
        string $key,
        int|\DateTimeInterface $ttl,
        callable $callback
    ) {
        $store = self::store();

        if ($store) {
            return $store->remember($key, self::resolveTtl($ttl), $callback);
        }

        $value = self::getFromMemory($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        self::putInMemory($key, $value, self::resolveTtl($ttl));

        return $value;
    }

    public static function add(string $key, $value, int $seconds): bool
    {
        $store = self::store();

        if ($store && $store->has($key)) {
            return false;
        }

        if ($store) {
            $store->put($key, $value, $seconds);
            return true;
        }

        if (self::getFromMemory($key) !== null) {
            return false;
        }

        self::putInMemory($key, $value, $seconds);

        return true;
    }

    private static function store(): ?CacheInterface
    {
        $container = Container::getInstance();

        if (!$container->has(CacheInterface::class)) {
            return null;
        }

        try {
            return $container->resolve(CacheInterface::class);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function putInMemory(string $key, mixed $value, int $seconds): void
    {
        self::$cache[$key] = [
            'value' => $value,
            'expires' => time() + $seconds,
        ];
    }

    private static function getFromMemory(string $key, mixed $default = null): mixed
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
}
