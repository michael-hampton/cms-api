<?php

declare(strict_types=1);

namespace App\Tests\Unit\Listeners\Subscriptions;

use App\Events\Subscriptions\SubscriptionCancelled;
use App\Events\Subscriptions\SubscriptionCancelledByStripe;
use App\Framework\Support\Logger;
use App\Listeners\Subscriptions\OnSubscriptionCancelledCancelFulfilments;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\FulfilmentCancellationService;
use Mockery;
use PHPUnit\Framework\TestCase;

class OnSubscriptionCancelledCancelFulfilmentsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_handle_loads_subscription_and_cancels_fulfilments(): void
    {
        $cancellationService = Mockery::mock(FulfilmentCancellationService::class);
        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 12;

        $subscriptionRepository->shouldReceive('find')->once()->with(12)->andReturn($subscription);
        $cancellationService->shouldReceive('cancel')->once()->with($subscription);

        $event = new SubscriptionCancelled(subscriptionId: 12, cancelAtPeriodEnd: false, endDate: null);

        $listener = new OnSubscriptionCancelledCancelFulfilments($cancellationService, $subscriptionRepository, $logger);
        $listener->handle($event);

        $this->assertTrue(true);
    }

    public function test_handle_logs_and_returns_when_subscription_not_found(): void
    {
        $cancellationService = Mockery::mock(FulfilmentCancellationService::class);
        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $subscriptionRepository->shouldReceive('find')->once()->with(12)->andReturn(null);
        $cancellationService->shouldReceive('cancel')->never();

        $event = new SubscriptionCancelled(subscriptionId: 12, cancelAtPeriodEnd: false, endDate: null);

        $listener = new OnSubscriptionCancelledCancelFulfilments($cancellationService, $subscriptionRepository, $logger);
        $listener->handle($event);

        $this->assertTrue(true);
    }

    public function test_handle_cancelled_by_stripe_uses_event_subscription_directly(): void
    {
        $cancellationService = Mockery::mock(FulfilmentCancellationService::class);
        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 12;

        $subscriptionRepository->shouldReceive('find')->never();
        $cancellationService->shouldReceive('cancel')->once()->with($subscription);

        $event = new SubscriptionCancelledByStripe(
            subscription: $subscription,
            cancelledAt: new \DateTimeImmutable(),
            accessUntil: new \DateTimeImmutable('+7 days'),
        );

        $listener = new OnSubscriptionCancelledCancelFulfilments($cancellationService, $subscriptionRepository, $logger);
        $listener->handleCancelledByStripe($event);

        $this->assertTrue(true);
    }

    public function test_swallows_and_logs_cancellation_failure(): void
    {
        $cancellationService = Mockery::mock(FulfilmentCancellationService::class);
        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $subscriptionRepository->shouldReceive('find')->andThrow(new \RuntimeException('boom'));

        $event = new SubscriptionCancelled(subscriptionId: 12, cancelAtPeriodEnd: false, endDate: null);

        $listener = new OnSubscriptionCancelledCancelFulfilments($cancellationService, $subscriptionRepository, $logger);
        $listener->handle($event);

        $this->assertTrue(true);
    }
}
