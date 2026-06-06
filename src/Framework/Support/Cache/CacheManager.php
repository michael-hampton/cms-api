<?php

namespace App\Framework\Support\Cache;

use App\Framework\Support\Cache\Contracts\CacheInterface;
use App\Framework\Support\Cache\Drivers\DatabaseCacheDriver;
use App\Framework\Support\Config;

class CacheManager
{
    public function __construct(
        private readonly DatabaseCacheDriver $databaseDriver
    ) {
    }

    public function driver(?string $name = null): CacheInterface
    {
        $name ??= Config::get('cache.driver', 'database');

        return match ($name) {
            'database' => $this->databaseDriver,
            default => $this->databaseDriver,
        };
    }
}
