<?php

namespace App\Framework\Support\Cache\Drivers;

use App\Framework\Support\Cache\Contracts\CacheInterface;
use App\Framework\Support\Logger;
use App\Repositories\Cache\CacheStoreRepository;

class DatabaseCacheDriver implements CacheInterface
{
    public function __construct(
        private readonly CacheStoreRepository $repository
    ) {
    }

    public function get(string $key): mixed
    {
        try {
            $item = $this->repository->find($key);

            if (!$item) {
                return null;
            }

            if ($this->isExpired((string) $item['expires_at'])) {
                $this->forget($key);
                return null;
            }

            return $this->decode((string) $item['value']);
        } catch (\Throwable $exception) {
            Logger::warning('Cache read failure', [
                'operation' => 'get',
                'cache_key' => $key,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function put(string $key, mixed $value, int $ttlSeconds): void
    {
        try {
            $expiresAt = date('Y-m-d H:i:s', time() + max(0, $ttlSeconds));
            $this->repository->upsert($key, $this->encode($value), $expiresAt);
        } catch (\Throwable $exception) {
            Logger::warning('Cache write failure', [
                'operation' => 'put',
                'cache_key' => $key,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function forget(string $key): void
    {
        try {
            $this->repository->delete($key);
        } catch (\Throwable $exception) {
            Logger::warning('Cache delete failure', [
                'operation' => 'forget',
                'cache_key' => $key,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function forgetMany(array $keys): void
    {
        try {
            $this->repository->deleteMany($keys);
        } catch (\Throwable $exception) {
            Logger::warning('Cache delete failure', [
                'operation' => 'forgetMany',
                'count' => count($keys),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function flush(): void
    {
        try {
            $this->repository->deleteAll();
        } catch (\Throwable $exception) {
            Logger::warning('Cache delete failure', [
                'operation' => 'flush',
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function remember(string $key, int $ttlSeconds, callable $callback): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->put($key, $value, $ttlSeconds);

        return $value;
    }

    public function prune(): int
    {
        try {
            return $this->repository->deleteExpired(date('Y-m-d H:i:s'));
        } catch (\Throwable $exception) {
            Logger::warning('Cache delete failure', [
                'operation' => 'prune',
                'error' => $exception->getMessage(),
            ]);

            return 0;
        }
    }

    private function isExpired(string $expiresAt): bool
    {
        return strtotime($expiresAt) <= time();
    }

    private function encode(mixed $value): string
    {
        return base64_encode(serialize($value));
    }

    private function decode(string $value): mixed
    {
        return unserialize(base64_decode($value), ['allowed_classes' => true]);
    }
}
