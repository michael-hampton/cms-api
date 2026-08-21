<?php

declare(strict_types=1);

namespace App\Tests\Unit\Listeners\Subscriptions;

use App\Events\Subscriptions\TrialConvertedEvent;
use App\Framework\Support\Logger;
use App\Listeners\Subscriptions\TrialConvertedListener;
use App\Models\Subscription;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class TrialConvertedListenerTest extends TestCase
{
    private Logger&MockInterface $logger;
    private TrialConvertedListener $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = Mockery::mock(Logger::class);
        $this->listener = new TrialConvertedListener($this->logger);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_logs_the_conversion(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 42;
        $subscription->member_id = 7;

        $this->logger
            ->shouldReceive('info')
            ->once()
            ->with(
                'TrialConvertedListener: trial converted to paid subscription',
                [
                    'subscription_id' => 42,
                    'member_id' => 7,
                ],
            );

        $this->listener->handle(new TrialConvertedEvent($subscription));

        // Mockery expectation verification (shouldReceive()->once()) isn't
        // counted as a PHPUnit assertion unless registered explicitly — this
        // class doesn't extend the codebase's UnitTestCase (whose
        // assertPostConditions() does that registration), so add an explicit
        // assertion to avoid a false "risky: no assertions" flag, matching
        // AssignInitialSubscriptionSegmentTest's existing pattern.
        $this->assertTrue(true);
    }
}
