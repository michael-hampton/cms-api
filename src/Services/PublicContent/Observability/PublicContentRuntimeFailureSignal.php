<?php

namespace App\Services\PublicContent\Observability;

/**
 * Named live-traffic signal for Public Content V2 runtime failures.
 *
 * Signal name: public_content.runtime_failure_rate
 */
final class PublicContentRuntimeFailureSignal
{
    public const string NAME = 'public_content.runtime_failure_rate';

    /** @var list<bool> */
    private array $window = [];

    public function __construct(
        private readonly int $windowSize = 100,
        private readonly float $threshold = 0.05,
    ) {
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function recordSuccess(): void
    {
        $this->push(true);
    }

    public function recordFailure(): void
    {
        $this->push(false);
    }

    /**
     * Inject a synthetic failure for pre-exposure proving. Does not depend on
     * real rollout traffic.
     */
    public function injectSyntheticFailure(): void
    {
        $this->recordFailure();
    }

    public function sampleCount(): int
    {
        return count($this->window);
    }

    public function failureRate(): float
    {
        $count = count($this->window);
        if ($count === 0) {
            return 0.0;
        }

        $failures = count(array_filter($this->window, static fn(bool $ok): bool => $ok === false));

        return $failures / $count;
    }

    public function threshold(): float
    {
        return $this->threshold;
    }

    public function isBreached(): bool
    {
        return count($this->window) >= max(1, (int) ceil($this->windowSize * 0.2))
            && $this->failureRate() >= $this->threshold;
    }

    private function push(bool $success): void
    {
        $this->window[] = $success;

        if (count($this->window) > $this->windowSize) {
            $this->window = array_slice($this->window, -$this->windowSize);
        }
    }
}
