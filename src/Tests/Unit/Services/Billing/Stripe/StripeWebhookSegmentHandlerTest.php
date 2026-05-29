<?php

namespace App\Tests\Unit\Services\Billing\Stripe;

use App\Models\Subscription;
use App\Services\Billing\Stripe\StripeWebhookSegmentHandler;
use App\Services\MemberInsights\Segmentation\SegmentAssignmentService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class StripeWebhookSegmentHandlerTest extends TestCase
{
    private SegmentAssignmentService|MockInterface $assignmentService;
    private StripeWebhookSegmentHandler $handler;

    public function test_it_evaluates_on_subscription_created(): void
    {
        $subscription = $this->makeSubscription(1);

        $this->assignmentService->expects('assignForSubscription')
            ->once()
            ->with($subscription);

        $this->handler->onSubscriptionCreated($subscription);

        $this->addToAssertionCount(1);
    }

    public function test_it_evaluates_on_subscription_updated(): void
    {
        $subscription = $this->makeSubscription(2);

        $this->assignmentService->expects('assignForSubscription')
            ->once()
            ->with($subscription);

        $this->handler->onSubscriptionUpdated($subscription);

        $this->addToAssertionCount(1);
    }

    public function test_it_evaluates_on_subscription_renewed(): void
    {
        $subscription = $this->makeSubscription(3);

        $this->assignmentService->expects('assignForSubscription')
            ->once()
            ->with($subscription);

        $this->handler->onSubscriptionRenewed($subscription);

        $this->addToAssertionCount(1);
    }

    public function test_it_evaluates_on_subscription_cancelled(): void
    {
        $subscription = $this->makeSubscription(4);

        $this->assignmentService->expects('assignForSubscription')
            ->once()
            ->with($subscription);

        $this->handler->onSubscriptionCancelled($subscription);

        $this->addToAssertionCount(1);
    }

    public function test_all_handlers_use_the_same_assignment_service_path(): void
    {
        // The key design invariant: no handler has its own assignment logic.
        // Four calls → four invocations of the same service method.
        $subscription = $this->makeSubscription(5);

        $this->assignmentService->expects('assignForSubscription')
            ->times(4)
            ->with($subscription)
            ->andReturnNull();

        $this->handler->onSubscriptionCreated($subscription);
        $this->handler->onSubscriptionUpdated($subscription);
        $this->handler->onSubscriptionRenewed($subscription);
        $this->handler->onSubscriptionCancelled($subscription);

        $this->addToAssertionCount(1);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeSubscription(int $id): Subscription
    {
        $sub     = Mockery::mock(Subscription::class)->makePartial();
        $sub->id = $id;

        return $sub;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->assignmentService = Mockery::mock(SegmentAssignmentService::class);
        $this->handler           = new StripeWebhookSegmentHandler($this->assignmentService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}