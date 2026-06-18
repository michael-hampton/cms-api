<?php

namespace App\Services\Resilience;

use Closure;

final class OperationContext
{
    public function __construct(
        private readonly int $startedAtMilliseconds,
        private readonly int $deadlineAtMilliseconds,
        private readonly int $timeoutMilliseconds,
        private readonly Closure $clock,
    ) {
    }

    public function timeoutMilliseconds(): int
    {
        return $this->timeoutMilliseconds;
    }

    public function elapsedMilliseconds(): int
    {
        return max(0, ($this->clock)() - $this->startedAtMilliseconds);
    }

    public function remainingMilliseconds(): int
    {
        return max(0, $this->deadlineAtMilliseconds - ($this->clock)());
    }

    public function throwIfExpired(): void
    {
        if ($this->remainingMilliseconds() === 0) {
            throw new OperationTimedOutException($this->timeoutMilliseconds);
        }
    }
}
