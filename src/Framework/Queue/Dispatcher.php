<?php

declare(strict_types=1);

namespace App\Framework\Queue;

use App\Framework\Support\Cache\Cache;
use App\Jobs\BaseJob;

/**
 * Pushes jobs onto the configured queue driver.
 *
 * dispatch() returns a PendingDispatch so callers can chain fluent
 * configuration (onQueue / delay / tries) before the job is actually pushed.
 *
 * dispatchNow() bypasses the queue and executes the job synchronously.
 */
class Dispatcher
{
    public function __construct(
        private readonly QueueDriverInterface $driver,
    )
    {
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Queue the job and return a PendingDispatch for fluent configuration.
     *
     * The job is pushed to the driver when the PendingDispatch is destructed,
     * so the caller can chain ->onQueue() / ->delay() without a terminal call.
     *
     *   dispatch(MyJob::for($id))->onQueue('default')->delay(60);
     */
    public function dispatch(Job $job): PendingDispatch
    {
        return new PendingDispatch($this, $job);
    }

    /**
     * Execute the job synchronously in the current process.
     */
    public function dispatchNow(Job $job): void
    {
        // In the async path the worker unserializes the job, which triggers __wakeup()
        // on BaseJob. For sync dispatch we must hydrate runtime dependencies manually.
        if ($job instanceof BaseJob) {
            $job->__wakeup();
        }

        $job->handle();
    }

    // -------------------------------------------------------------------------
    // Internal — called by PendingDispatch::dispatch()
    // -------------------------------------------------------------------------

    /**
     * Push the fully-configured job onto the driver.
     *
     * Handles the ShouldBeUnique contract: if a lock already exists the job is
     * silently dropped (identical to the previous behaviour).
     *
     * @internal Called by PendingDispatch, not directly by application code.
     */
    public function push(Job $job): void
    {
        if ($job instanceof ShouldBeUnique) {
            $key = 'unique_job:' . $job->uniqueId();

            if (!Cache::add($key, true, $job->uniqueFor())) {
                return;
            }
        }

        $this->driver->push($job);
    }
}