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
     * @template TResult
     * @param callable(OperationContext, int): TResult $operation
     * @return TResult
     */
    public function execute(callable $operation): mixed
    {
        $breaker = self::$circuitBreaker ??= CircuitBreaker::withSystemClock();
        $executor = ResilientOperationExecutor::withSystemClock($breaker);

        return $executor->execute(
            $operation,
            static fn(Throwable $exception): bool => $exception instanceof RetriableOperationException,
        );
    }
}
