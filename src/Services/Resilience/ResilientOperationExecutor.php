<?php

namespace App\Services\Resilience;

use Closure;
use Throwable;

final class ResilientOperationExecutor
{
    public function __construct(
        private readonly CircuitBreaker $circuitBreaker,
        private readonly Closure $clock,
        private readonly Closure $sleeper,
        private readonly int $timeoutMilliseconds = 1_500,
        private readonly int $retryBackoffMilliseconds = 100,
        private readonly int $maxRetries = 1,
    ) {
    }

    public static function withSystemClock(CircuitBreaker $circuitBreaker): self
    {
        return new self(
            circuitBreaker: $circuitBreaker,
            clock: static fn(): int => (int) floor(microtime(true) * 1000),
            sleeper: static fn(int $milliseconds): mixed => usleep($milliseconds * 1000),
        );
    }

    /**
     * @template TResult
     * @param callable(OperationContext, int): TResult $operation
     * @param callable(Throwable): bool $isRetriable
     * @return TResult
     */
    public function execute(callable $operation, callable $isRetriable): mixed
    {
        $this->circuitBreaker->beforeCall();

        $startedAt = ($this->clock)();
        $deadlineAt = $startedAt + $this->timeoutMilliseconds;
        $context = new OperationContext(
            startedAtMilliseconds: $startedAt,
            deadlineAtMilliseconds: $deadlineAt,
            timeoutMilliseconds: $this->timeoutMilliseconds,
            clock: $this->clock,
        );

        $attempt = 0;

        while (true) {
            $attempt++;
            $context->throwIfExpired();

            try {
                $result = $operation($context, $attempt);
                $context->throwIfExpired();
                $this->circuitBreaker->recordSuccess();

                return $result;
            } catch (Throwable $exception) {
                $retriable = $attempt <= $this->maxRetries && $isRetriable($exception);

                if (!$retriable) {
                    $this->circuitBreaker->recordFailure();
                    throw $exception;
                }

                if ($context->remainingMilliseconds() <= $this->retryBackoffMilliseconds) {
                    $this->circuitBreaker->recordFailure();
                    throw new OperationTimedOutException($this->timeoutMilliseconds);
                }

                ($this->sleeper)($this->retryBackoffMilliseconds);
                $context->throwIfExpired();
            }
        }
    }
}
