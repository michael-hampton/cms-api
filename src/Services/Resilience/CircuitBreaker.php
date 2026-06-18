<?php

namespace App\Services\Resilience;

use Closure;

final class CircuitBreaker
{
    private const string CLOSED = 'closed';
    private const string OPEN = 'open';
    private const string HALF_OPEN = 'half_open';

    private string $state = self::CLOSED;

    /** @var list<int> */
    private array $failureTimestamps = [];

    private ?int $openedAtMilliseconds = null;
    private bool $probeInFlight = false;
    private int $successfulProbes = 0;

    public function __construct(
        private readonly Closure $clock,
        private readonly int $failureThreshold = 5,
        private readonly int $failureWindowMilliseconds = 10_000,
        private readonly int $openDurationMilliseconds = 30_000,
        private readonly int $successfulProbesToClose = 2,
    ) {
    }

    public static function withSystemClock(): self
    {
        return new self(
            static fn(): int => (int) floor(microtime(true) * 1000),
        );
    }

    public function beforeCall(): void
    {
        $now = ($this->clock)();

        if ($this->state === self::CLOSED) {
            return;
        }

        if ($this->state === self::OPEN) {
            if ($this->openedAtMilliseconds === null
                || ($now - $this->openedAtMilliseconds) < $this->openDurationMilliseconds) {
                throw new CircuitOpenException();
            }

            $this->state = self::HALF_OPEN;
            $this->probeInFlight = false;
            $this->successfulProbes = 0;
        }

        if ($this->probeInFlight) {
            throw new CircuitOpenException();
        }

        $this->probeInFlight = true;
    }

    public function recordSuccess(): void
    {
        if ($this->state === self::HALF_OPEN) {
            $this->probeInFlight = false;
            $this->successfulProbes++;

            if ($this->successfulProbes >= $this->successfulProbesToClose) {
                $this->close();
            }

            return;
        }

        $this->failureTimestamps = [];
    }

    public function recordFailure(): void
    {
        $now = ($this->clock)();

        if ($this->state === self::HALF_OPEN) {
            $this->open($now);
            return;
        }

        $windowStart = $now - $this->failureWindowMilliseconds;
        $this->failureTimestamps = array_values(array_filter(
            $this->failureTimestamps,
            static fn(int $timestamp): bool => $timestamp >= $windowStart,
        ));
        $this->failureTimestamps[] = $now;

        if (count($this->failureTimestamps) >= $this->failureThreshold) {
            $this->open($now);
        }
    }

    public function state(): string
    {
        return $this->state;
    }

    private function open(int $now): void
    {
        $this->state = self::OPEN;
        $this->openedAtMilliseconds = $now;
        $this->probeInFlight = false;
        $this->successfulProbes = 0;
    }

    private function close(): void
    {
        $this->state = self::CLOSED;
        $this->failureTimestamps = [];
        $this->openedAtMilliseconds = null;
        $this->probeInFlight = false;
        $this->successfulProbes = 0;
    }
}
