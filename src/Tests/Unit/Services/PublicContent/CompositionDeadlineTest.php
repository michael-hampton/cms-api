<?php

namespace App\Tests\Unit\Services\PublicContent;

use App\Services\PublicContent\CompositionDeadline;
use App\Services\Resilience\OperationContext;
use Mockery;
use PHPUnit\Framework\TestCase;

final class CompositionDeadlineTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_unlimited_always_has_budget(): void
    {
        $deadline = CompositionDeadline::unlimited();

        self::assertSame(PHP_INT_MAX, $deadline->remainingMilliseconds());
        self::assertTrue($deadline->hasBudget(1_000_000));
    }

    public function test_it_reports_remaining_budget_from_a_positive_source(): void
    {
        $deadline = new CompositionDeadline(static fn(): int => 250);

        self::assertSame(250, $deadline->remainingMilliseconds());
        self::assertTrue($deadline->hasBudget(200));
        self::assertFalse($deadline->hasBudget(300));
    }

    public function test_it_never_returns_a_negative_remaining_budget(): void
    {
        $deadline = new CompositionDeadline(static fn(): int => -500);

        self::assertSame(0, $deadline->remainingMilliseconds());
        self::assertFalse($deadline->hasBudget(1));
    }

    public function test_it_has_budget_for_a_zero_cost_operation_even_when_exhausted(): void
    {
        $deadline = new CompositionDeadline(static fn(): int => 0);

        self::assertTrue($deadline->hasBudget(0));
    }

    public function test_from_context_delegates_to_operation_context(): void
    {
        $context = Mockery::mock(OperationContext::class);
        $context->shouldReceive('remainingMilliseconds')->once()->andReturn(75);

        $deadline = CompositionDeadline::fromContext($context);

        self::assertSame(75, $deadline->remainingMilliseconds());
    }
}