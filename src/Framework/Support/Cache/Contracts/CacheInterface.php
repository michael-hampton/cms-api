<?php

namespace App\Framework\Support\Cache\Contracts;

interface CacheInterface
{
    public function get(string $key): mixed;

    public function put(string $key, mixed $value, int $ttlSeconds): void;

    public function forget(string $key): void;

    public function forgetMany(array $keys): void;

    public function flush(): void;

    public function has(string $key): bool;

    public function remember(string $key, int $ttlSeconds, callable $callback): mixed;
}
