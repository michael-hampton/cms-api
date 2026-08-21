<?php

declare(strict_types=1);

namespace App\Tests\Unit\Listeners\Subscriptions;

use App\Events\Subscriptions\SubscriptionPlanChanged;
use App\Framework\Support\Logger;
use App\Listeners\Subscriptions\SubscriptionPlanChangedListener;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class SubscriptionPlanChangedListenerTest extends TestCase
{
    private Logger&MockInterface $logger;
    private SubscriptionPlanChangedListener $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = Mockery::mock(Logger::class);
        $this->listener = new SubscriptionPlanChangedListener($this->logger);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_logs_the_plan_change(): void
    {
        $this->logger
            ->shouldReceive('info')
            ->once()
            ->with(
                'SubscriptionPlanChangedListener: plan changed',
                [
                    'subscription_id' => 42,
                    'old_plan_id' => 5,
                    'new_plan_id' => 6,
                    'agent_id' => 99,
                    'timestamp' => '2027-03-01 09:30:00',
                ],
            );

        $this->listener->handle(new SubscriptionPlanChanged(42, 5, 6, 99, '2027-03-01 09:30:00'));

        // Mockery expectation verification (shouldReceive()->once()) isn't
        // counted as a PHPUnit assertion unless registered explicitly — this
        // class doesn't extend the codebase's UnitTestCase (whose
        // assertPostConditions() does that registration), so add an explicit
        // assertion to avoid a false "risky: no assertions" flag, matching
        // AssignInitialSubscriptionSegmentTest's existing pattern.
        $this->assertTrue(true);
    }
}
