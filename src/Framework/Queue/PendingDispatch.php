<?php

declare(strict_types=1);

namespace App\Framework\Queue;

/**
 * Returned by the dispatch() helper.
 *
 * Accumulates queue/connection/delay configuration via fluent setters and
 * pushes the job to the queue exactly once — in the destructor — so the
 * caller never needs to call a terminal method.
 *
 * Usage:
 *   dispatch(MyJob::for($id))
 *       ->onQueue('print')
 *       ->delay(now_datetime()->addMinutes(5));
 *
 * The job is dispatched when the PendingDispatch goes out of scope.
 */
class PendingDispatch
{
    private bool $dispatched = false;

    public function __construct(
        private readonly Dispatcher $dispatcher,
        private readonly Job        $job,
    )
    {
    }

    // -------------------------------------------------------------------------
    // Fluent configuration
    // -------------------------------------------------------------------------

    public function onQueue(string $queue): static
    {
        $this->job->onQueue($queue);

        return $this;
    }

    public function onConnection(string $connection): static
    {
        $this->job->onConnection($connection);

        return $this;
    }

    /**
     * Accept either an integer number of seconds or a future DateTimeInterface.
     *
     * When a DateTimeInterface is supplied the offset from now is computed and
     * stored as seconds, matching the int-based contract of Job::$delay.
     */
    public function delay(\DateTimeInterface|int $delay): static
    {
        if ($delay instanceof \DateTimeInterface) {
            $seconds = max(0, $delay->getTimestamp() - time());
            $this->job->delay($seconds);
        } else {
            $this->job->delay($delay);
        }

        return $this;
    }

    /**
     * Override the number of attempts before the job is considered failed.
     */
    public function tries(int $tries): static
    {
        $this->job->tries = $tries;

        return $this;
    }

    /**
     * Override the per-attempt timeout in seconds.
     */
    public function timeout(int $seconds): static
    {
        $this->job->timeout = $seconds;

        return $this;
    }

    // -------------------------------------------------------------------------
    // Terminal dispatch
    // -------------------------------------------------------------------------

    /**
     * Run the job synchronously in the current process, bypassing the queue.
     * Useful in tests or for fire-and-forget console commands.
     */
    public function dispatchNow(): void
    {
        $this->dispatched = true; // Prevent double-dispatch in destructor.
        $this->dispatcher->dispatchNow($this->job);
    }

    /**
     * Push to the queue when the pending dispatch falls out of scope.
     *
     * This mirrors Laravel's approach: the caller can chain setters without
     * needing a terminal ->send() call, and the job is still dispatched.
     */
    public function __destruct()
    {
        $this->dispatch();
    }

    // -------------------------------------------------------------------------
    // Destructor — the key to the fluent API
    // -------------------------------------------------------------------------

    /**
     * Explicitly push the job to the queue now, before the destructor fires.
     * Calling this is optional — the destructor handles the common case.
     */
    public function dispatch(): void
    {
        if ($this->dispatched) {
            return;
        }

        $this->dispatched = true;
        $this->dispatcher->push($this->job);
    }
}