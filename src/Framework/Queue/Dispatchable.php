<?php

namespace App\Framework\Queue;

use App\Framework\Support\Cache\Cache;

trait Dispatchable
{
    public function dispatch(Job $job): Job
    {
        if ($job instanceof ShouldBeUnique) {
            $key = 'unique_job:' . $job->uniqueId();

            if (!Cache::add($key, true, $job->uniqueFor())) {
                return $job;
            }
        }

        $this->driver->push($job);

        return $job;
    }
}