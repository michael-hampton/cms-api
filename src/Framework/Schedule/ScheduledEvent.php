<?php

namespace App\Framework\Schedule;

use App\Framework\Queue\JobInterface;

class ScheduledEvent
{
    private JobInterface $job;
    private ?int $lastRunTime = null;
    private ?int $intervalSeconds = null;

    public function __construct(JobInterface $job)
    {
        $this->job = $job;
    }

    public function everyMinute(): self
    {
        $this->intervalSeconds = 60;
        return $this;
    }

    public function everyFiveMinutes(): self
    {
        $this->intervalSeconds = 300;
        return $this;
    }

    public function everyTenMinutes(): self
    {
        $this->intervalSeconds = 600;
        return $this;
    }

    public function everyFifteenMinutes(): self
    {
        $this->intervalSeconds = 900;
        return $this;
    }

    public function everyThirtyMinutes(): self
    {
        $this->intervalSeconds = 1800;
        return $this;
    }

    public function hourly(): self
    {
        $this->intervalSeconds = 3600;
        return $this;
    }

    public function daily(): self
    {
        $this->intervalSeconds = 86400;
        return $this;
    }

    public function weekly(): self
    {
        $this->intervalSeconds = 604800;
        return $this;
    }

    public function isDue(int $now): bool
    {
        if ($this->lastRunTime === null) {
            return true;
        }

        if ($this->intervalSeconds !== null) {
            return ($now - $this->lastRunTime) >= $this->intervalSeconds;
        }

        return false;
    }

    public function markAsRun(int $time): void
    {
        $this->lastRunTime = $time;
    }

    public function getJob(): JobInterface
    {
        return $this->job;
    }

    public function getExpression(): string
    {
        if ($this->intervalSeconds) {
            return "every {$this->intervalSeconds} seconds";
        }
        return 'unknown';
    }
}