<?php

declare(strict_types=1);

namespace App\Tests\Unit\Listeners\Subscriptions;

use App\Events\Subscriptions\SubscriptionEditionChanged;
use App\Framework\Support\Logger;
use App\Listeners\Subscriptions\SubscriptionEditionChangedListener;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class SubscriptionEditionChangedListenerTest extends TestCase
{
    private Logger&MockInterface $logger;
    private SubscriptionEditionChangedListener $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = Mockery::mock(Logger::class);
        $this->listener = new SubscriptionEditionChangedListener($this->logger);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_logs_the_edition_change(): void
    {
        $this->logger
            ->shouldReceive('info')
            ->once()
            ->with(
                'SubscriptionEditionChangedListener: edition changed',
                [
                    'subscription_id' => 42,
                    'old_edition_id' => 1,
                    'new_edition_id' => 2,
                    'agent_id' => 99,
                    'timestamp' => '2027-03-01 09:30:00',
                ],
            );

        $this->listener->handle(new SubscriptionEditionChanged(42, 1, 2, 99, '2027-03-01 09:30:00'));

        // Mockery expectation verification (shouldReceive()->once()) isn't
        // counted as a PHPUnit assertion unless registered explicitly — this
        // class doesn't extend the codebase's UnitTestCase (whose
        // assertPostConditions() does that registration), so add an explicit
        // assertion to avoid a false "risky: no assertions" flag, matching
        // AssignInitialSubscriptionSegmentTest's existing pattern.
        $this->assertTrue(true);
    }
}
