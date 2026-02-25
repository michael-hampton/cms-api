<?php

namespace App\Framework\Queue;


use App\Framework\Support\Cache\Cache;

class Dispatcher
{
    protected QueueDriverInterface $driver;

    public function __construct(QueueDriverInterface $driver)
    {
        $this->driver = $driver;
    }

    public function dispatch(Job $job): void
    {
        // Unique handling
        if ($job instanceof ShouldBeUnique) {
            $key = 'unique_job:' . $job->uniqueId();

            if (!Cache::add($key, true, $job->uniqueFor())) {
                return; // Already queued
            }
        }

        $this->driver->push($job);
    }

    public function dispatchNow(Job $job): void
    {
        $job->handle();
    }
}