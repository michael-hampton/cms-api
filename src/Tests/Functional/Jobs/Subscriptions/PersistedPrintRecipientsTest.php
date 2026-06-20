<?php

declare(strict_types=1);

namespace App\Tests\Functional\Jobs\Subscriptions;

use App\Enums\Subscriptions\IssueDeliveredStatus;
use App\Enums\Subscriptions\IssueScheduleStatus;
use App\Enums\Subscriptions\PrintRunStatus;
use App\Enums\Subscriptions\SubscriptionType;
use App\Framework\Container;
use App\Framework\Queue\Dispatcher;
use App\Framework\Queue\Job;
use App\Framework\Queue\QueueDriverInterface;
use App\Framework\Support\Logger;
use App\Jobs\Subscriptions\CreateFulfilmentsChunkJob;
use App\Jobs\Subscriptions\CreatePrintFulfillmentsJob;
use App\Jobs\Subscriptions\FulfilmentCompletionMonitorJob;
use App\Models\IssueDelivery;
use App\Models\IssuesDelivered;
use App\Models\PrintRun;
use App\Models\Subscription;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\IssuesDeliveredRepository;
use App\Repositories\Subscriptions\PrintRunRepository;
use App\Services\Workflow\WorkflowRunRecorderFactory;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery;

class PersistedPrintRecipientsTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_persisted_print_rows_are_chunked_without_digital_or_undispatched_rows(): void
    {
        $plan = $this->createSubscriptionPlan();
        $issue = IssueDelivery::create([
            'site_id' => $this->siteId,
            'subscription_plan_id' => $plan->id,
            'issue_number' => 1,
            'issue_title' => 'Print Issue',
            'status' => IssueScheduleStatus::ACTIVE->value,
            'on_sale_date' => (new \DateTime('-1 day'))->format('Y-m-d H:i:s'),
            'estimated_delivery_date' => (new \DateTime('-1 minute'))->format('Y-m-d H:i:s'),
        ]);
        $expectedIds = [];

        for ($index = 0; $index < 450; $index++) {
            $subscription = $this->subscription($plan->id, SubscriptionType::PRINTED->value);
            $expectedIds[] = $subscription->id;
            $this->fulfilment($subscription->id, $issue->id, true);
        }

        $digital = $this->subscription($plan->id, SubscriptionType::DIGITAL->value);
        $this->fulfilment($digital->id, $issue->id, true);
        $pending = $this->subscription($plan->id, SubscriptionType::PRINTED->value);
        $this->fulfilment($pending->id, $issue->id, false);

        $jobs = [];
        $queue = Mockery::mock(QueueDriverInterface::class);
        $queue->shouldReceive('push')->andReturnUsing(function (Job $job) use (&$jobs) {
            $jobs[] = $job;
        });
        $printRun = Mockery::mock(PrintRun::class)->makePartial();
        $printRun->id = 1;
        $printRun->status = PrintRunStatus::PENDING->value;
        $printRun->shouldReceive('isCancelled')->andReturn(false);
        $printRun->shouldReceive('markFulfilling')->once()->with(3);
        $printRuns = Mockery::mock(PrintRunRepository::class);
        $printRuns->shouldReceive('find')->with(1)->andReturn($printRun);
        $issues = Mockery::mock(IssueDeliveryRepository::class);
        $issues->shouldReceive('find')->with($issue->id)->andReturn($issue);

        $container = Container::getInstance();
        $container->instance(PrintRunRepository::class, $printRuns);
        $container->instance(IssueDeliveryRepository::class, $issues);
        $container->instance(IssuesDeliveredRepository::class, new IssuesDeliveredRepository());
        $container->instance(WorkflowRunRecorderFactory::class, Mockery::mock(WorkflowRunRecorderFactory::class)->shouldIgnoreMissing());
        $container->instance(Logger::class, Mockery::mock(Logger::class)->shouldIgnoreMissing());
        $container->instance(Dispatcher::class, new Dispatcher($queue));

        $job = CreatePrintFulfillmentsJob::for(1, $issue->id);
        $job->__wakeup();
        $job->handle();

        $chunks = array_values(array_filter($jobs, fn($job) => $job instanceof CreateFulfilmentsChunkJob));
        $actualIds = [];
        foreach ($chunks as $chunk) {
            $actualIds = array_merge($actualIds, $chunk->subscriptionIds());
        }
        sort($expectedIds);

        $this->assertCount(3, $chunks);
        $this->assertSame($expectedIds, $actualIds);
        $this->assertSame([200, 200, 50], array_map(fn($chunk) => count($chunk->subscriptionIds()), $chunks));
        $this->assertSame([0, 1, 2], array_map(fn($chunk) => $chunk->chunkIndex(), $chunks));
        $this->assertNotContains($digital->id, $actualIds);
        $this->assertNotContains($pending->id, $actualIds);
        $this->assertCount(1, array_filter($jobs, fn($job) => $job instanceof FulfilmentCompletionMonitorJob));
    }

    private function subscription(int $planId, string $type): Subscription
    {
        return Subscription::create([
            'plan_id' => $planId,
            'member_id' => $this->createMember()->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'type' => 'paid',
            'delivery_type' => $type,
        ]);
    }

    private function fulfilment(int $subscriptionId, int $issueId, bool $dispatched): void
    {
        IssuesDelivered::create([
            'subscription_id' => $subscriptionId,
            'issue_delivery_id' => $issueId,
            'status' => IssueDeliveredStatus::SCHEDULED->value,
            'attempts' => 0,
            'scheduled_for' => (new \DateTime('-1 minute'))->format('Y-m-d H:i:s'),
            'dispatched_at' => $dispatched ? (new \DateTime())->format('Y-m-d H:i:s') : null,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
