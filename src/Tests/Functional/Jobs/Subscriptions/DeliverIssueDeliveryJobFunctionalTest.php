<?php

declare(strict_types=1);

namespace App\Tests\Functional\Jobs\Subscriptions;

use App\Enums\Subscriptions\IssueDeliveredStatus;
use App\Enums\Subscriptions\IssueScheduleStatus;
use App\Enums\Subscriptions\SubscriptionType;
use App\Framework\Container;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Jobs\Subscriptions\DeliverIssueDeliveryJob;
use App\Models\IssueDelivery;
use App\Models\IssuesDelivered;
use App\Models\Subscription;
use App\Repositories\Subscriptions\IssuesDeliveredRepository;
use App\Services\Subscriptions\DeliveryChannelInterface;
use App\Services\Subscriptions\DeliveryService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery;

class DeliverIssueDeliveryJobFunctionalTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_success_marks_the_same_fulfilment_delivered(): void
    {
        [$fulfilment] = $this->createDigitalFulfilment();
        $channel = new class implements DeliveryChannelInterface {
            public int $calls = 0;

            public function send(Subscription $subscription, IssueDelivery $issueDelivery): void
            {
                $this->calls++;
            }
        };

        $this->runJob($fulfilment->id, $channel);
        $reloaded = IssuesDelivered::find($fulfilment->id);

        $this->assertSame(1, $channel->calls);
        $this->assertSame(IssueDeliveredStatus::DELIVERED->value, $reloaded->status);
        $this->assertNotNull($reloaded->delivered_at);
        $this->assertSame(1, IssuesDelivered::where('subscription_id', $fulfilment->subscription_id)
            ->where('issue_delivery_id', $fulfilment->issue_delivery_id)
            ->count());
    }

    public function test_failure_records_attempt_and_retry_reuses_the_same_row(): void
    {
        [$fulfilment] = $this->createDigitalFulfilment();
        $failingChannel = new class implements DeliveryChannelInterface {
            public function send(Subscription $subscription, IssueDelivery $issueDelivery): void
            {
                throw new \RuntimeException('Simulated delivery failure');
            }
        };

        try {
            $this->runJob($fulfilment->id, $failingChannel);
            $this->fail('Expected the delivery job to rethrow the channel failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated delivery failure', $exception->getMessage());
        }

        $failed = IssuesDelivered::find($fulfilment->id);
        $this->assertSame(IssueDeliveredStatus::FAILED->value, $failed->status);
        $this->assertSame(1, $failed->attempts);
        $this->assertNotNull($failed->failed_at);
        $this->assertStringContainsString('Simulated delivery failure', $failed->failure_reason);

        $successfulChannel = new class implements DeliveryChannelInterface {
            public function send(Subscription $subscription, IssueDelivery $issueDelivery): void
            {
            }
        };

        $this->runJob($fulfilment->id, $successfulChannel);
        $delivered = IssuesDelivered::find($fulfilment->id);

        $this->assertSame($fulfilment->id, $delivered->id);
        $this->assertSame(IssueDeliveredStatus::DELIVERED->value, $delivered->status);
        $this->assertNotNull($delivered->delivered_at);
        $this->assertSame(1, IssuesDelivered::where('subscription_id', $fulfilment->subscription_id)
            ->where('issue_delivery_id', $fulfilment->issue_delivery_id)
            ->count());
    }

    public function test_retry_repository_stops_returning_rows_at_the_attempt_limit(): void
    {
        [$fulfilment] = $this->createDigitalFulfilment();
        $repository = new IssuesDeliveredRepository();

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $fulfilment = IssuesDelivered::find($fulfilment->id);
            $fulfilment->markAsFailed('Failure ' . $attempt);
        }

        $retriable = $repository->getFailedRetriable(3);

        $this->assertFalse($retriable->pluck('id')->contains($fulfilment->id));
        $this->assertFalse(IssuesDelivered::find($fulfilment->id)->canRetry(3));
    }

    private function createDigitalFulfilment(): array
    {
        $plan = $this->createSubscriptionPlan();
        $subscription = Subscription::create([
            'member_id' => $this->createMember()->id,
            'site_id' => $this->siteId,
            'plan_id' => $plan->id,
            'plan_name' => 'Digital Plan',
            'status' => 'active',
            'type' => 'paid',
            'delivery_type' => SubscriptionType::DIGITAL->value,
        ]);
        $issue = IssueDelivery::create([
            'site_id' => $this->siteId,
            'subscription_plan_id' => $plan->id,
            'subscription_id' => null,
            'issue_number' => 1,
            'issue_title' => 'Digital Issue',
            'status' => IssueScheduleStatus::ACTIVE->value,
            'on_sale_date' => (new \DateTime('-1 day'))->format('Y-m-d H:i:s'),
            'estimated_delivery_date' => (new \DateTime('-1 minute'))->format('Y-m-d H:i:s'),
        ]);
        $fulfilment = IssuesDelivered::create([
            'subscription_id' => $subscription->id,
            'issue_delivery_id' => $issue->id,
            'status' => IssueDeliveredStatus::SCHEDULED->value,
            'attempts' => 0,
            'scheduled_for' => (new \DateTime('-1 minute'))->format('Y-m-d H:i:s'),
            'dispatched_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);

        return [$fulfilment, $subscription, $issue];
    }

    private function runJob(int $fulfilmentId, DeliveryChannelInterface $channel): void
    {
        $container = Container::getInstance();
        $container->instance(IssuesDeliveredRepository::class, new IssuesDeliveredRepository());
        $container->instance(DeliveryService::class, new DeliveryService());
        $container->instance(Logger::class, Mockery::mock(Logger::class)->shouldIgnoreMissing());
        $container->instance(Database::class, $container->resolve(Database::class));

        $job = DeliverIssueDeliveryJob::for(
            $fulfilmentId,
            [SubscriptionType::DIGITAL->value => $channel]
        );
        $job->__wakeup();
        $job->handle();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
