<?php

declare(strict_types=1);

namespace App\Tests\Unit\Listeners\Subscriptions;

use App\Events\Subscriptions\SubscriptionCreated;
use App\Framework\Support\Logger;
use App\Listeners\Subscriptions\SendSubscriptionLifecycleCommunicationListener;
use App\Models\Subscription;
use App\Models\SubscriptionCommunication;
use App\Repositories\Subscriptions\SubscriptionCommunicationRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\Communications\SubscriptionCommunicationSender;
use Mockery;
use PHPUnit\Framework\TestCase;

class SendSubscriptionLifecycleCommunicationListenerTest extends TestCase
{
    private SubscriptionRepository $subscriptions;
    private SubscriptionCommunicationRepository $communications;
    private SubscriptionCommunicationSender $sender;
    private Logger $logger;
    private SendSubscriptionLifecycleCommunicationListener $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptions = Mockery::mock(SubscriptionRepository::class);
        $this->communications = Mockery::mock(SubscriptionCommunicationRepository::class);
        $this->sender = Mockery::mock(SubscriptionCommunicationSender::class);
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $this->listener = new SendSubscriptionLifecycleCommunicationListener(
            $this->subscriptions,
            $this->communications,
            $this->sender,
            $this->logger,
        );
    }

    public function test_subscription_created_sends_acknowledgement_communication(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 42;

        $communication = Mockery::mock(SubscriptionCommunication::class)->makePartial();
        $communication->id = 1;

        $event = new SubscriptionCreated(
            subscriptionId: 42,
            planId: 7,
            billingPeriod: 'annual',
            priceCents: 4999,
            currency: 'GBP',
        );

        $this->subscriptions->shouldReceive('find')->once()->with(42)->andReturn($subscription);
        $this->communications->shouldReceive('findActiveByKey')
            ->once()
            ->with('acknowledgement_default')
            ->andReturn($communication);

        $this->sender->shouldReceive('send')
            ->once()
            ->with(Mockery::on(fn ($args) => true), $communication, null, Mockery::type('array'), Mockery::type('string'));

        $this->listener->handleSubscriptionCreated($event);

        $this->assertTrue(true);
    }

    public function test_subscription_created_skips_when_subscription_not_found(): void
    {
        $event = new SubscriptionCreated(
            subscriptionId: 99,
            planId: 7,
            billingPeriod: 'annual',
            priceCents: 4999,
            currency: 'GBP',
        );

        $this->subscriptions->shouldReceive('find')->once()->with(99)->andReturn(null);
        $this->communications->shouldReceive('findActiveByKey')->never();
        $this->sender->shouldReceive('send')->never();

        $this->listener->handleSubscriptionCreated($event);

        $this->assertTrue(true);
    }

    public function test_subscription_created_skips_when_no_active_communication_configured(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 42;

        $event = new SubscriptionCreated(
            subscriptionId: 42,
            planId: 7,
            billingPeriod: 'annual',
            priceCents: 4999,
            currency: 'GBP',
        );

        $this->subscriptions->shouldReceive('find')->once()->andReturn($subscription);
        $this->communications->shouldReceive('findActiveByKey')->once()->andReturn(null);
        $this->sender->shouldReceive('send')->never();

        $this->listener->handleSubscriptionCreated($event);

        $this->assertTrue(true);
    }
}
