<?php

namespace App\Tests\Unit\Services\Resilience;

use App\Services\Resilience\CircuitBreaker;
use App\Services\Resilience\CircuitOpenException;
use PHPUnit\Framework\TestCase;

final class CircuitBreakerTest extends TestCase
{
    public function test_opens_after_five_failures_in_window(): void
    {
        $now = 0;
        $breaker = new CircuitBreaker(static function () use (&$now): int {
            return $now;
        });

        for ($index = 0; $index < 5; $index++) {
            $breaker->beforeCall();
            $breaker->recordFailure();
            $now += 1000;
        }

        self::assertSame('open', $breaker->state());
        $this->expectException(CircuitOpenException::class);
        $breaker->beforeCall();
    }

    public function test_closes_after_two_successful_half_open_probes(): void
    {
        $now = 0;
        $breaker = new CircuitBreaker(static function () use (&$now): int {
            return $now;
        });

        for ($index = 0; $index < 5; $index++) {
            $breaker->beforeCall();
            $breaker->recordFailure();
        }

        $now = 30000;

        $breaker->beforeCall();
        $breaker->recordSuccess();
        self::assertSame('half_open', $breaker->state());

        $breaker->beforeCall();
        $breaker->recordSuccess();
        self::assertSame('closed', $breaker->state());
    }
}
