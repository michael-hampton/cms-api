<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Enums\Subscriptions\IssueDeliveryStatus;
use App\Jobs\Subscriptions\DeliverIssueDeliveryJob;
use App\Models\IssuesDelivered;
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
        $subscription = $this->createSubscription();
        $issuesDelivered = IssuesDelivered::create([
            'status' => 'scheduled',
            'subscription_id' => $subscription->id,
            'issue_delivery_id' => $issueDelivery->id,
            'attempts' => 0,
        ]);

        $deliveryService = Mockery::mock(DeliveryService::class);
        $deliveryService->shouldReceive('send')->once();
        app()->instance(DeliveryService::class, $deliveryService);

        $job = app(DeliverIssueDeliveryJob::class);
        $job->handle($issuesDelivered->id);

        $issuesDelivered = IssuesDelivered::find($issuesDelivered->id);

        $this->assertEquals(IssueDeliveryStatus::DELIVERED->value, $issuesDelivered->status);
        $this->assertNotNull($issuesDelivered->delivered_at);
    }

    public function test_marks_delivery_as_failed_and_increments_attempts_on_failure(): void
    {
        $issueDelivery = $this->createIssueDelivery();
        $subscription = $this->createSubscription();
        $issuesDelivered = IssuesDelivered::create([
            'status' => IssueDeliveryStatus::SCHEDULED->value,
            'subscription_id' => $subscription->id,
            'issue_delivery_id' => $issueDelivery->id,
            'attempts' => 0,
        ]);

        $deliveryService = Mockery::mock(DeliveryService::class);
        $deliveryService->shouldReceive('send')
            ->andThrow(new \Exception('Delivery failed'));
        app()->instance(DeliveryService::class, $deliveryService);

        $job = app(DeliverIssueDeliveryJob::class);

        try {
            $job->handle($issuesDelivered->id);
        } catch (\Exception $e) {
            // Expected
        }

        $issuesDelivered = IssuesDelivered::find($issuesDelivered->id);

        $this->assertEquals(IssueDeliveryStatus::FAILED->value, $issuesDelivered->status);
        $this->assertEquals(1, $issuesDelivered->attempts);
        $this->assertStringContainsString('Delivery failed', $issuesDelivered->failure_reason);
    }

    public function test_skips_already_delivered_idempotent(): void
    {
        $issueDelivery = $this->createIssueDelivery();
        $subscription = $this->createSubscription();
        $issuesDelivered = IssuesDelivered::create([
            'status' => IssueDeliveryStatus::DELIVERED->value,
            'subscription_id' => $subscription->id,
            'issue_delivery_id' => $issueDelivery->id,
            'delivered_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);

        $deliveryService = Mockery::mock(DeliveryService::class);
        $deliveryService->shouldNotReceive('send');
        app()->instance(DeliveryService::class, $deliveryService);

        $job = app(DeliverIssueDeliveryJob::class);
        $job->handle($issuesDelivered->id);

        $issuesDelivered = IssuesDelivered::find($issuesDelivered->id);
        $this->assertEquals(IssueDeliveryStatus::DELIVERED->value, $issuesDelivered->status);
    }
}