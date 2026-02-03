<?php

namespace App\Services\RateLimiting;

use App\Framework\Support\Cache\FileCache;

class RateLimiter implements RateLimiterInterface
{
    private string $prefix = 'rate_limiter:';

    public function __construct(private readonly FileCache $cache)
    {
    }

    public function attempt(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $key = $this->formatKey($key);

        $data = $this->cache->get($key);

        // First attempt
        if ($data === null) {
            $this->cache->put($key, [
                'attempts' => 1,
                'expires_at' => time() + $decaySeconds,
            ], $decaySeconds);

            return true;
        }

        if ($data['attempts'] >= $maxAttempts) {
            return false;
        }

        $data['attempts']++;

        $remaining = max(1, $data['expires_at'] - time());

        $this->cache->put($key, $data, $remaining);

        return true;
    }

    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        $key = $this->formatKey($key);

        $data = $this->cache->get($key);

        if ($data === null) {
            return false;
        }

        return $data['attempts'] >= $maxAttempts;
    }

    public function availableIn(string $key): int
    {
        $key = $this->formatKey($key);

        $data = $this->cache->get($key);

        if ($data === null) {
            return 0;
        }

        return max(0, $data['expires_at'] - time());
    }

    private function formatKey(string $key): string
    {
        return $this->prefix . $key;
    }
}
