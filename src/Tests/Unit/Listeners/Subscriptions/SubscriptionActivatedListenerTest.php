<?php

declare(strict_types=1);

namespace App\Tests\Unit\Listeners\Subscriptions;

use App\Events\Subscriptions\SubscriptionActivated;
use App\Framework\Support\Logger;
use App\Listeners\Subscriptions\SubscriptionActivatedListener;
use DateTimeImmutable;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class SubscriptionActivatedListenerTest extends TestCase
{
    private Logger&MockInterface $logger;
    private SubscriptionActivatedListener $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = Mockery::mock(Logger::class);
        $this->listener = new SubscriptionActivatedListener($this->logger);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_logs_the_activation(): void
    {
        $activatedAt = new DateTimeImmutable('2027-03-01 09:30:00');

        $this->logger
            ->shouldReceive('info')
            ->once()
            ->with(
                'SubscriptionActivatedListener: subscription activated',
                [
                    'subscription_id' => 42,
                    'activated_at' => '2027-03-01 09:30:00',
                ],
            );

        $this->listener->handle(new SubscriptionActivated(42, $activatedAt));

        // Mockery expectation verification (shouldReceive()->once()) isn't
        // counted as a PHPUnit assertion unless registered explicitly — this
        // class doesn't extend the codebase's UnitTestCase (whose
        // assertPostConditions() does that registration), so add an explicit
        // assertion to avoid a false "risky: no assertions" flag, matching
        // AssignInitialSubscriptionSegmentTest's existing pattern.
        $this->assertTrue(true);
    }
}
