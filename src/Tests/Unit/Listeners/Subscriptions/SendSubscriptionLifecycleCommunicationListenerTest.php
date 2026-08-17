<?php

declare(strict_types=1);

namespace App\Tests\Unit\Listeners\Subscriptions;

use App\Events\Subscriptions\SubscriptionCreated;
use App\Events\Subscriptions\SubscriptionProductChanged;
use App\Events\Subscriptions\SubscriptionRenewedAndReplaced;
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

    public function test_subscription_renewed_and_replaced_sends_communication_for_new_subscription(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 55;

        $communication = Mockery::mock(SubscriptionCommunication::class)->makePartial();
        $communication->id = 2;

        $event = new SubscriptionRenewedAndReplaced(
            memberId: 10,
            oldSubscriptionId: 42,
            newSubscriptionId: 55,
            productId: 3,
            planId: 7,
            amountPaid: 49.99,
            timestamp: '2026-01-01 00:00:00',
        );

        $this->subscriptions->shouldReceive('find')->once()->with(55)->andReturn($subscription);
        $this->communications->shouldReceive('findActiveByKey')
            ->once()
            ->with('subscription_renewed_default')
            ->andReturn($communication);

        $this->sender->shouldReceive('send')->once();

        $this->listener->handleSubscriptionRenewedAndReplaced($event);

        $this->assertTrue(true);
    }

    public function test_subscription_product_changed_sends_communication_for_new_subscription(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 66;

        $communication = Mockery::mock(SubscriptionCommunication::class)->makePartial();
        $communication->id = 3;

        $event = new SubscriptionProductChanged(
            memberId: 10,
            oldSubscriptionId: 44,
            newSubscriptionId: 66,
            oldPlanId: 5,
            newPlanId: 8,
            switchMode: 'transfer',
            carriedOverCredit: 4.50,
            agentId: 1,
            timestamp: '2026-01-01 00:00:00',
        );

        $this->subscriptions->shouldReceive('find')->once()->with(66)->andReturn($subscription);
        $this->communications->shouldReceive('findActiveByKey')
            ->once()
            ->with('subscription_product_changed_default')
            ->andReturn($communication);

        $this->sender->shouldReceive('send')->once();

        $this->listener->handleSubscriptionProductChanged($event);

        $this->assertTrue(true);
    }
}
