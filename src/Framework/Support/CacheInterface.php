<?php

namespace App\Framework\Support;

interface CacheInterface
{
    public function put(string $key, $value, int $seconds = 3600): void;
    public function get(string $key, $default = null);
    public function forget(string $key): void;
    public function flush(): void;
}