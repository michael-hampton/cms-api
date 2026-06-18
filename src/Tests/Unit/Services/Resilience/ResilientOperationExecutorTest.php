<?php

namespace App\Tests\Unit\Services\Resilience;

use App\Services\Resilience\CircuitBreaker;
use App\Services\Resilience\OperationTimedOutException;
use App\Services\Resilience\ResilientOperationExecutor;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ResilientOperationExecutorTest extends TestCase
{
    public function test_retries_once_after_retriable_failure(): void
    {
        $now = 0;
        $attempts = 0;
        $executor = $this->executor($now);

        $result = $executor->execute(
            function () use (&$attempts): string {
                $attempts++;
                if ($attempts === 1) {
                    throw new RuntimeException('temporary');
                }
                return 'ok';
            },
            static fn(\Throwable $exception): bool => $exception->getMessage() === 'temporary',
        );

        self::assertSame('ok', $result);
        self::assertSame(2, $attempts);
        self::assertSame(100, $now);
    }

    public function test_timeout_budget_includes_backoff_and_retry(): void
    {
        $now = 0;
        $executor = $this->executor($now, 150);

        $this->expectException(OperationTimedOutException::class);

        $executor->execute(
            function () use (&$now): void {
                $now += 60;
                throw new RuntimeException('temporary');
            },
            static fn(): bool => true,
        );
    }

    private function executor(int &$now, int $timeoutMilliseconds = 1500): ResilientOperationExecutor
    {
        $clock = static function () use (&$now): int {
            return $now;
        };

        return new ResilientOperationExecutor(
            circuitBreaker: new CircuitBreaker($clock),
            clock: $clock,
            sleeper: static function (int $milliseconds) use (&$now): void {
                $now += $milliseconds;
            },
            timeoutMilliseconds: $timeoutMilliseconds,
        );
    }
}
