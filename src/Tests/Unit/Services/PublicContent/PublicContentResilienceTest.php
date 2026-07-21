<?php

namespace App\Tests\Unit\Services\PublicContent;

use App\Services\PublicContent\CompositionDeadline;
use App\Services\PublicContent\PublicContentResilience;
use App\Services\Resilience\CircuitBreaker;
use App\Services\Resilience\OperationTimedOutException;
use App\Services\Resilience\ResilientOperationExecutor;
use PHPUnit\Framework\TestCase;

final class PublicContentResilienceTest extends TestCase
{
    protected function tearDown(): void
    {
        PublicContentResilience::resetCircuitBreaker();
        parent::tearDown();
    }

    public function test_default_timeout_keeps_shared_resilience_budget(): void
    {
        $resilience = new PublicContentResilience();

        self::assertSame(1500, $resilience->timeoutMilliseconds());
    }

    public function test_explicit_timeout_is_honoured(): void
    {
        $resilience = new PublicContentResilience(2_500);

        self::assertSame(2500, $resilience->timeoutMilliseconds());
    }

    public function test_timeout_still_surfaces_when_work_checks_deadline_before_starting(): void
    {
        $resilience = new PublicContentResilience(1);

        $this->expectException(OperationTimedOutException::class);

        $resilience->execute(function ($context): void {
            usleep(5_000);
            $context->throwIfExpired();
        });
    }

    public function test_completed_result_is_not_discarded_after_deadline_slip(): void
    {
        $now = 0;
        $clock = static function () use (&$now): int {
            return $now;
        };

        $executor = new ResilientOperationExecutor(
            circuitBreaker: new CircuitBreaker($clock),
            clock: $clock,
            sleeper: static function (int $milliseconds) use (&$now): void {
                $now += $milliseconds;
            },
            timeoutMilliseconds: 100,
        );

        $result = $executor->execute(
            function () use (&$now): string {
                $now += 150; // slip past deadline during work
                return 'composed';
            },
            static fn(): bool => false,
        );

        self::assertSame('composed', $result);
    }

    public function test_composition_deadline_reports_budget(): void
    {
        $deadline = new CompositionDeadline(static fn(): int => 250);

        self::assertTrue($deadline->hasBudget(250));
        self::assertFalse($deadline->hasBudget(251));
    }
}
