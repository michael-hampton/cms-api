<?php

namespace App\Services\Shared;

interface SessionStore
{
    public function get(string $key, mixed $default = null): mixed;

    public function put(string $key, mixed $value): void;

    public function has(string $key): bool;
}