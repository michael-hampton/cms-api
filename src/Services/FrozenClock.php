<?php

namespace App\Services;

use App\Contracts\ClockInterface;

class FrozenClock implements ClockInterface
{
    public function __construct(private readonly \DateTimeImmutable $frozenAt)
    {
    }

    public function now(): \DateTimeImmutable
    {
        return $this->frozenAt;
    }
}