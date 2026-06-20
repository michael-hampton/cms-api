<?php

declare(strict_types=1);

namespace App\Tests\Functional\Jobs\Subscriptions;

use App\Enums\Subscriptions\IssueDeliveryStatus;
use App\Enums\Subscriptions\SubscriptionType;
use App\Events\Subscriptions\IssueDeliveryDispatched;
use App\Framework\Container;
use App\Framework\Support\Logger;
use App\Jobs\Subscriptions\GenerateIssueDeliveriesJob;
use App\Models\IssueDelivery;
use App\Models\IssuesDelivered;
use App\Models\Subscription;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\IssuesDeliveredRepository;
use App\Services\Subscriptions\IssueDeliveryEligibilityService;
use App\Services\Subscriptions\IssueFulfilmentDispatchCoordinator;
use App\Services\Subscriptions\IssueFulfilmentPlanner;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Support\CapturingEventDispatcher;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery;

class GenerateIssueDeliveriesFulfilmentTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_print_subscription_is_persisted_claimed_and_dispatched_from_the_job(): void
    {
        $plan = $this->createSubscriptionPlan();
        $subscription = Subscription::create([
            'member_id' => $this->createMember()->id,
            'site_id' => $this->siteId,
            'plan_id' => $plan->id,
            'plan_name' => 'Print Plan',
            'status' => 'active',
            'type' => 'paid',
            'delivery_type' => SubscriptionType::PRINTED->value,
        ]);
        $issue = IssueDelivery::create([
            'site_id' => $this->siteId,
            'subscription_plan_id' => $plan->id,
            'subscription_id' => null,
            'issue_number' => 1,
            'issue_title' => 'Issue One',
            'status' => IssueDeliveryStatus::ACTIVE->value,
            'on_sale_date' => (new \DateTime('-2 days'))->format('Y-m-d H:i:s'),
            'estimated_delivery_date' => (new \DateTime('-1 minute'))->format('Y-m-d H:i:s'),
        ]);

        $issueRepository = Mockery::mock(IssueDeliveryRepository::class);
        $issueRepository->shouldReceive('find')->once()->with($issue->id)->andReturn($issue);
        $eligibility = Mockery::mock(IssueDeliveryEligibilityService::class);
        $eligibility->shouldReceive('getEligibleSubscriptions')
            ->once()->with($issue)->andReturn(collect([$subscription]));
        $fulfilmentRepository = new IssuesDeliveredRepository();
        $logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();
        $events = CapturingEventDispatcher::fake();

        $container = Container::getInstance();
        $container->instance(IssueDeliveryRepository::class, $issueRepository);
        $container->instance(IssueDeliveryEligibilityService::class, $eligibility);
        $container->instance(IssuesDeliveredRepository::class, $fulfilmentRepository);
        $container->instance(
            IssueFulfilmentPlanner::class,
            new IssueFulfilmentPlanner($fulfilmentRepository)
        );
        $container->instance(
            IssueFulfilmentDispatchCoordinator::class,
            new IssueFulfilmentDispatchCoordinator($fulfilmentRepository, $logger)
        );
        $container->instance(Logger::class, $logger);

        $job = GenerateIssueDeliveriesJob::for($issue->id);
        $job->__wakeup();
        $result = $job->handle();

        $fulfilment = IssuesDelivered::where('subscription_id', $subscription->id)
            ->where('issue_delivery_id', $issue->id)
            ->first();

        $this->assertNotNull($fulfilment);
        $this->assertNotNull($fulfilment->scheduled_for);
        $this->assertNotNull($fulfilment->dispatched_at);
        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['print_dispatches']);
        $this->assertSame(0, $result['digital_dispatches']);
        $events->assertDispatched(IssueDeliveryDispatched::class, function ($event) use ($issue) {
            return $event->issueDelivery->id === $issue->id
                && $event->eligibleCount === 1;
        });
        $this->assertSame(
            IssueDeliveryStatus::DISPATCHED->value,
            IssueDelivery::find($issue->id)->status
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
