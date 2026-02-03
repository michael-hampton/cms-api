<?php

namespace App\Services\RateLimiting;

interface RateLimiterInterface
{
    public function attempt(string $key, int $maxAttempts, int $decaySeconds): bool;

    public function tooManyAttempts(string $key, int $maxAttempts): bool;

    public function availableIn(string $key): int;
}