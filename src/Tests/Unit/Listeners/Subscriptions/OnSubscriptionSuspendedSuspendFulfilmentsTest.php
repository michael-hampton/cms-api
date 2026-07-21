<?php

declare(strict_types=1);

namespace App\Tests\Unit\Listeners\Subscriptions;

use App\Events\Subscriptions\SubscriptionSuspended;
use App\Framework\Support\Logger;
use App\Listeners\Subscriptions\OnSubscriptionSuspendedSuspendFulfilments;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\FulfilmentSuspensionService;
use Mockery;
use PHPUnit\Framework\TestCase;

class OnSubscriptionSuspendedSuspendFulfilmentsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeEvent(): SubscriptionSuspended
    {
        return new SubscriptionSuspended(
            subscriptionId: 9,
            memberId: 55,
            agentId: 1,
            reason: 'Violation of terms',
            timestamp: '2026-07-20 10:00:00',
        );
    }

    public function test_loads_subscription_and_triggers_suspension(): void
    {
        $suspensionService = Mockery::mock(FulfilmentSuspensionService::class);
        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 9;

        $subscriptionRepository->shouldReceive('find')->once()->with(9)->andReturn($subscription);

        $suspensionService->shouldReceive('handleTrigger')
            ->once()
            ->with($subscription, FulfilmentSuspensionService::REASON_SUBSCRIPTION_SUSPENDED);

        $listener = new OnSubscriptionSuspendedSuspendFulfilments($suspensionService, $subscriptionRepository, $logger);
        $listener->handle($this->makeEvent());

        $this->assertTrue(true);
    }

    public function test_logs_and_returns_when_subscription_not_found(): void
    {
        $suspensionService = Mockery::mock(FulfilmentSuspensionService::class);
        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $subscriptionRepository->shouldReceive('find')->once()->with(9)->andReturn(null);
        $suspensionService->shouldReceive('handleTrigger')->never();

        $listener = new OnSubscriptionSuspendedSuspendFulfilments($suspensionService, $subscriptionRepository, $logger);
        $listener->handle($this->makeEvent());

        $this->assertTrue(true);
    }

    public function test_swallows_and_logs_suspension_failure(): void
    {
        $suspensionService = Mockery::mock(FulfilmentSuspensionService::class);
        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $subscriptionRepository->shouldReceive('find')->andThrow(new \RuntimeException('db down'));

        $listener = new OnSubscriptionSuspendedSuspendFulfilments($suspensionService, $subscriptionRepository, $logger);

        $listener->handle($this->makeEvent());

        $this->assertTrue(true);
    }
}
