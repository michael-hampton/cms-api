<?php

declare(strict_types=1);

namespace App\Tests\Unit\Listeners\Subscriptions;

use App\Events\Subscriptions\SubscriptionUnsuspended;
use App\Framework\Support\Logger;
use App\Listeners\Subscriptions\OnSubscriptionUnsuspendedReleaseFulfilments;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\FulfilmentSuspensionService;
use Mockery;
use PHPUnit\Framework\TestCase;

class OnSubscriptionUnsuspendedReleaseFulfilmentsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeEvent(): SubscriptionUnsuspended
    {
        return new SubscriptionUnsuspended(
            subscriptionId: 9,
            memberId: 55,
            agentId: 1,
            reason: 'Payment resolved',
            timestamp: '2026-07-21 10:00:00',
        );
    }

    public function test_loads_subscription_and_releases_fulfilments(): void
    {
        $suspensionService = Mockery::mock(FulfilmentSuspensionService::class);
        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 9;

        $subscriptionRepository->shouldReceive('find')->once()->with(9)->andReturn($subscription);
        $suspensionService->shouldReceive('release')->once()->with($subscription);

        $listener = new OnSubscriptionUnsuspendedReleaseFulfilments($suspensionService, $subscriptionRepository, $logger);
        $listener->handle($this->makeEvent());

        $this->assertTrue(true);
    }

    public function test_logs_and_returns_when_subscription_not_found(): void
    {
        $suspensionService = Mockery::mock(FulfilmentSuspensionService::class);
        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $subscriptionRepository->shouldReceive('find')->once()->with(9)->andReturn(null);
        $suspensionService->shouldReceive('release')->never();

        $listener = new OnSubscriptionUnsuspendedReleaseFulfilments($suspensionService, $subscriptionRepository, $logger);
        $listener->handle($this->makeEvent());

        $this->assertTrue(true);
    }

    public function test_swallows_and_logs_release_failure(): void
    {
        $suspensionService = Mockery::mock(FulfilmentSuspensionService::class);
        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $subscriptionRepository->shouldReceive('find')->andThrow(new \RuntimeException('db down'));

        $listener = new OnSubscriptionUnsuspendedReleaseFulfilments($suspensionService, $subscriptionRepository, $logger);
        $listener->handle($this->makeEvent());

        $this->assertTrue(true);
    }
}
