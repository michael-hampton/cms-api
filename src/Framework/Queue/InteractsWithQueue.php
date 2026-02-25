<?php

namespace App\Framework\Queue;

trait InteractsWithQueue
{
    protected ?Job $job = null;

    public function setJob(Job $job): void
    {
        $this->job = $job;
    }

    public function delete(): void
    {
        $this->job?->delete();
    }

    public function release(int $delay = 0): void
    {
        $this->job?->release($delay);
    }

    public function fail(\Throwable $e): void
    {
        $this->job?->fail($e);
    }
}