<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Enums\Subscriptions\IssueDeliveredStatus;
use App\Enums\Subscriptions\SubscriptionType;
use App\Framework\Container;
use App\Jobs\Subscriptions\DeliverIssueDeliveryJob;
use App\Models\IssuesDelivered;
use App\Services\Subscriptions\DeliveryChannels\EmailDeliveryChannel;
use App\Services\Subscriptions\DeliveryChannels\PrintDeliveryChannel;
use App\Services\Subscriptions\DeliveryService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery;

class DeliverIssueDeliveryJobTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_marks_delivery_as_delivered_on_success(): void
    {
        $issueDelivery = $this->createIssueDelivery();
        $subscription = $this->createSubscription(['delivery_type' => 'digital']);
        $issuesDelivered = IssuesDelivered::create([
            'status' => IssueDeliveredStatus::SCHEDULED->value,
            'subscription_id' => $subscription->id,
            'issue_delivery_id' => $issueDelivery->id,
            'attempts' => 0,
        ]);

        $deliveryService = Mockery::mock(DeliveryService::class);
        $deliveryService->shouldReceive('registerChannel')->byDefault();
        $deliveryService->shouldReceive('send')->once();
        app()->instance(DeliveryService::class, $deliveryService);

        $job = DeliverIssueDeliveryJob::for(
            $issuesDelivered->id,
            [
                SubscriptionType::DIGITAL->value => app()->make(EmailDeliveryChannel::class),
                SubscriptionType::PRINTED->value => app()->make(PrintDeliveryChannel::class),
            ]
        );
        $job->__wakeup();
        $job->handle();

        $issuesDelivered->refresh();

        $this->assertEquals(IssueDeliveredStatus::DELIVERED->value, $issuesDelivered->status);
        $this->assertNotNull($issuesDelivered->delivered_at);
    }

    public function test_marks_delivery_as_failed_and_increments_attempts_on_failure(): void
    {
        $issueDelivery = $this->createIssueDelivery();
        $subscription = $this->createSubscription(['delivery_type' => 'digital']);
        $issuesDelivered = IssuesDelivered::create([
            'status' => IssueDeliveredStatus::SCHEDULED->value, // was incorrectly IssueDeliveryStatus
            'subscription_id' => $subscription->id,
            'issue_delivery_id' => $issueDelivery->id,
            'attempts' => 0,
        ]);

        $deliveryService = Mockery::mock(DeliveryService::class);
        $deliveryService->shouldReceive('registerChannel')->byDefault();
        $deliveryService->shouldReceive('send')->andThrow(new \Exception('Delivery failed'));
        Container::getInstance()->instance(DeliveryService::class, $deliveryService);

        $job = DeliverIssueDeliveryJob::for(
            $issuesDelivered->id,
            [
                SubscriptionType::DIGITAL->value => app()->make(EmailDeliveryChannel::class),
                SubscriptionType::PRINTED->value => app()->make(PrintDeliveryChannel::class),
            ]
        );
        $job->__wakeup();

        try {
            $job->handle();
        } catch (\Exception) {
            // Expected — job re-throws after marking failure.
        }

        $issuesDelivered->refresh();

        $this->assertEquals(IssueDeliveredStatus::FAILED->value, $issuesDelivered->status);
        $this->assertEquals(1, $issuesDelivered->attempts);
        $this->assertStringContainsString('Delivery failed', $issuesDelivered->failure_reason);
    }


    public function test_skips_already_delivered_idempotent(): void
    {
        $issueDelivery = $this->createIssueDelivery();
        $subscription = $this->createSubscription();
        $issuesDelivered = IssuesDelivered::create([
            'status' => IssueDeliveredStatus::DELIVERED->value,
            'subscription_id' => $subscription->id,
            'issue_delivery_id' => $issueDelivery->id,
            'delivered_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);

        $deliveryService = Mockery::mock(DeliveryService::class);
        $deliveryService->shouldReceive('registerChannel');
        $deliveryService->shouldNotReceive('send');
        Container::getInstance()->instance(DeliveryService::class, $deliveryService);

        $job = DeliverIssueDeliveryJob::for($issuesDelivered->id);
        $job->__wakeup();
        $job->handle();

        $issuesDelivered = IssuesDelivered::find($issuesDelivered->id);
        $this->assertEquals(IssueDeliveredStatus::DELIVERED->value, $issuesDelivered->status);
    }
}