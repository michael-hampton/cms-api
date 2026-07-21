<?php

namespace App\Services\PublicContent;

use App\Services\Resilience\CircuitBreaker;
use App\Services\Resilience\OperationContext;
use App\Services\Resilience\ResilientOperationExecutor;
use App\Services\Resilience\RetriableOperationException;
use Throwable;

final class PublicContentResilience
{
    private static ?CircuitBreaker $circuitBreaker = null;

    /**
     * Keep the shared resilience budget. Slow islands must degrade inside
     * this window — do not inflate the deadline to paper over blocking work.
     */
    private const int DEFAULT_TIMEOUT_MILLISECONDS = 1_500;

    public function __construct(
        private readonly ?int $timeoutMilliseconds = null,
    ) {
    }

    /**
     * @template TResult
     * @param callable(OperationContext, int): TResult $operation
     * @return TResult
     */
    public function execute(callable $operation): mixed
    {
        $breaker = self::$circuitBreaker ??= CircuitBreaker::withSystemClock();
        $executor = ResilientOperationExecutor::withSystemClock(
            $breaker,
            $this->resolveTimeoutMilliseconds(),
        );

        return $executor->execute(
            $operation,
            static fn(Throwable $exception): bool => $exception instanceof RetriableOperationException,
        );
    }

    public function timeoutMilliseconds(): int
    {
        return $this->resolveTimeoutMilliseconds();
    }

    /** @internal testing */
    public static function resetCircuitBreaker(): void
    {
        self::$circuitBreaker = null;
    }

    private function resolveTimeoutMilliseconds(): int
    {
        if ($this->timeoutMilliseconds !== null) {
            return max(1, $this->timeoutMilliseconds);
        }

        $configured = config('public_content.runtime.timeout_milliseconds', null);
        if (is_numeric($configured)) {
            return max(1, (int) $configured);
        }

        $fromEnv = env('PUBLIC_CONTENT_V2_TIMEOUT_MS', self::DEFAULT_TIMEOUT_MILLISECONDS);

        return max(1, (int) $fromEnv);
    }
}
