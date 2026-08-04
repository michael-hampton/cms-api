<?php

declare(strict_types=1);

namespace App\Tests\Unit\Listeners\Subscriptions;

use App\Events\Subscriptions\SubscriptionCreated;
use App\Framework\Support\Logger;
use App\Listeners\Subscriptions\AssignInitialSubscriptionSegment;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\MemberInsights\Segmentation\SegmentAssignmentService;
use Mockery;
use PHPUnit\Framework\TestCase;

class AssignInitialSubscriptionSegmentTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeEvent(int $subscriptionId = 42): SubscriptionCreated
    {
        return new SubscriptionCreated(
            subscriptionId: $subscriptionId,
            planId: 10,
            billingPeriod: 'monthly',
            priceCents: 999,
            currency: 'GBP',
        );
    }

    public function test_it_loads_the_subscription_and_evaluates_assignment(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 42;

        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $subscriptionRepository->shouldReceive('find')->once()->with(42)->andReturn($subscription);

        $assignmentService = Mockery::mock(SegmentAssignmentService::class);
        $assignmentService->shouldReceive('assignForSubscription')->once()->with($subscription);

        $logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $listener = new AssignInitialSubscriptionSegment($subscriptionRepository, $assignmentService, $logger);
        $listener->handle($this->makeEvent(42));

        $this->assertTrue(true);
    }

    public function test_it_does_nothing_when_subscription_no_longer_exists(): void
    {
        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $subscriptionRepository->shouldReceive('find')->once()->with(42)->andReturn(null);

        $assignmentService = Mockery::mock(SegmentAssignmentService::class);
        $assignmentService->shouldNotReceive('assignForSubscription');

        $logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $listener = new AssignInitialSubscriptionSegment($subscriptionRepository, $assignmentService, $logger);
        $listener->handle($this->makeEvent(42));

        $this->assertTrue(true);
    }

    public function test_it_swallows_and_logs_assignment_failure(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 42;

        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $subscriptionRepository->shouldReceive('find')->once()->andReturn($subscription);

        $assignmentService = Mockery::mock(SegmentAssignmentService::class);
        $assignmentService->shouldReceive('assignForSubscription')->andThrow(new \RuntimeException('boom'));

        $logger = Mockery::mock(Logger::class);
        $logger->shouldReceive('error')->once()->with(
            'AssignInitialSubscriptionSegment: segment evaluation failed',
            Mockery::on(fn(array $context): bool => $context['subscription_id'] === 42)
        );

        $listener = new AssignInitialSubscriptionSegment($subscriptionRepository, $assignmentService, $logger);
        $listener->handle($this->makeEvent(42));

        $this->assertTrue(true);
    }
}