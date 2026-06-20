<?php

declare(strict_types=1);

namespace App\Tests\Functional\Jobs\Subscriptions;

use App\Enums\Subscriptions\IssueDeliveryStatus;
use App\Enums\Subscriptions\SubscriptionType;
use App\Events\Subscriptions\IssueDeliveryDispatched;
use App\Framework\Container;
use App\Framework\Queue\Dispatcher;
use App\Framework\Queue\Job;
use App\Framework\Queue\QueueDriverInterface;
use App\Framework\Support\Logger;
use App\Jobs\Subscriptions\DeliverIssueDeliveryJob;
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

    private array $queuedJobs = [];

    public function test_generation_persists_only_eligible_digital_and_print_fulfilments(): void
    {
        [$issue, $digital, $print, $cancelled, $events] = $this->prepareGeneration();

        $result = $this->runJob($issue->id);

        $rows = IssuesDelivered::where('issue_delivery_id', $issue->id)->get();

        $this->assertCount(2, $rows);
        $this->assertTrue($rows->pluck('subscription_id')->contains($digital->id));
        $this->assertTrue($rows->pluck('subscription_id')->contains($print->id));
        $this->assertFalse($rows->pluck('subscription_id')->contains($cancelled->id));
        $this->assertSame(1, IssueDelivery::where('subscription_plan_id', $issue->subscription_plan_id)->count());

        foreach ($rows as $row) {
            $this->assertSame($issue->id, $row->issue_delivery_id);
            $this->assertNotNull($row->scheduled_for);
            $this->assertNotNull($row->dispatched_at);
        }

        $this->assertCount(1, $this->queuedJobsOfType(DeliverIssueDeliveryJob::class));
        $events->assertDispatched(IssueDeliveryDispatched::class, function ($event) use ($issue) {
            return $event->issueDelivery->id === $issue->id
                && $event->eligibleCount === 1;
        });
        $this->assertSame(2, $result['created']);
        $this->assertSame(1, $result['digital_dispatches']);
        $this->assertSame(1, $result['print_dispatches']);
        $this->assertSame(
            IssueDeliveryStatus::DISPATCHED->value,
            IssueDelivery::find($issue->id)->status
        );
    }

    public function test_generation_rerun_is_idempotent_and_does_not_repeat_handoffs(): void
    {
        [$issue, $digital, $print, $cancelled, $events] = $this->prepareGeneration(2);

        $first = $this->runJob($issue->id);
        $firstQueueCount = count($this->queuedJobs);
        $firstEventCount = count($events->all());
        $firstRows = IssuesDelivered::where('issue_delivery_id', $issue->id)->get();
        $firstIds = $firstRows->pluck('id')->toArray();

        $second = $this->runJob($issue->id);
        $secondRows = IssuesDelivered::where('issue_delivery_id', $issue->id)->get();

        $this->assertCount(2, $secondRows);
        $this->assertSame($firstIds, $secondRows->pluck('id')->toArray());
        $this->assertSame($firstQueueCount, count($this->queuedJobs));
        $this->assertSame($firstEventCount, count($events->all()));
        $this->assertSame(2, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(0, $second['digital_dispatches']);
        $this->assertSame(0, $second['print_dispatches']);
        $this->assertSame(2, $second['already_dispatched']);
        $this->assertTrue($secondRows->pluck('subscription_id')->contains($digital->id));
        $this->assertTrue($secondRows->pluck('subscription_id')->contains($print->id));
        $this->assertFalse($secondRows->pluck('subscription_id')->contains($cancelled->id));
        $this->assertSame(
            IssueDeliveryStatus::DISPATCHED->value,
            IssueDelivery::find($issue->id)->status
        );
    }

    private function prepareGeneration(int $runs = 1): array
    {
        $plan = $this->createSubscriptionPlan();
        $digital = $this->createSubscription($plan->id, SubscriptionType::DIGITAL->value, 'active');
        $print = $this->createSubscription($plan->id, SubscriptionType::PRINTED->value, 'active');
        $cancelled = $this->createSubscription($plan->id, SubscriptionType::PRINTED->value, 'cancelled');
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
        $issueRepository->shouldReceive('find')->times($runs)->with($issue->id)->andReturn($issue);
        $eligibility = Mockery::mock(IssueDeliveryEligibilityService::class);
        $eligibility->shouldReceive('getEligibleSubscriptions')
            ->times($runs)
            ->with($issue)
            ->andReturn(collect([$digital, $print]));
        $fulfilmentRepository = new IssuesDeliveredRepository();
        $logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();
        $events = CapturingEventDispatcher::fake();
        $queueDriver = Mockery::mock(QueueDriverInterface::class);
        $queueDriver->shouldReceive('push')->andReturnUsing(function (Job $job) {
            $this->queuedJobs[] = $job;
        });

        $container = Container::getInstance();
        $container->instance(IssueDeliveryRepository::class, $issueRepository);
        $container->instance(IssueDeliveryEligibilityService::class, $eligibility);
        $container->instance(IssuesDeliveredRepository::class, $fulfilmentRepository);
        $container->instance(IssueFulfilmentPlanner::class, new IssueFulfilmentPlanner($fulfilmentRepository));
        $container->instance(
            IssueFulfilmentDispatchCoordinator::class,
            new IssueFulfilmentDispatchCoordinator($fulfilmentRepository, $logger)
        );
        $container->instance(Logger::class, $logger);
        $container->instance(Dispatcher::class, new Dispatcher($queueDriver));

        return [$issue, $digital, $print, $cancelled, $events];
    }

    private function runJob(int $issueId): array
    {
        $job = GenerateIssueDeliveriesJob::for($issueId);
        $job->__wakeup();
        return $job->handle();
    }

    private function createSubscription(int $planId, string $deliveryType, string $status): Subscription
    {
        return Subscription::create([
            'member_id' => $this->createMember()->id,
            'site_id' => $this->siteId,
            'plan_id' => $planId,
            'plan_name' => 'Test Plan',
            'status' => $status,
            'type' => 'paid',
            'delivery_type' => $deliveryType,
        ]);
    }

    private function queuedJobsOfType(string $class): array
    {
        return array_values(array_filter($this->queuedJobs, function ($job) use ($class) {
            return $job instanceof $class;
        }));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
