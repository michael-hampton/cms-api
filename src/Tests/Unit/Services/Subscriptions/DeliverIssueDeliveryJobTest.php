<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Enums\Subscriptions\SubscriptionIssueFulfilmentStatus;
use App\Enums\Subscriptions\SubscriptionType;
use App\Events\Subscriptions\SubscriptionFirstIssueDelivered;
use App\Framework\Container;
use App\Framework\Events\EventDispatcher;
use App\Framework\Support\Logger; // 1. Import the Logger class
use App\Jobs\Subscriptions\DeliverIssueDeliveryJob;
use App\Models\SubscriptionIssueFulfilment;
use App\Services\Subscriptions\DeliveryChannels\EmailDeliveryChannel;
use App\Services\Subscriptions\DeliveryChannels\PrintDeliveryChannel;
use App\Services\Subscriptions\DeliveryService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery;

class DeliverIssueDeliveryJobTest extends FunctionalTestCase
{
    use CreatesTestData;

    // 2. Add the setUp method to handle container binding for the Logger
    protected function setUp(): void
    {
        parent::setUp();

        $logger = Mockery::mock(Logger::class);
        $logger->shouldReceive('info')->byDefault();
        $logger->shouldReceive('warning')->byDefault();
        $logger->shouldReceive('error')->byDefault();

        app()->instance(Logger::class, $logger);
    }

    public function test_marks_delivery_as_delivered_on_success(): void
    {
        $issueDelivery = $this->createIssueDelivery();
        $subscription = $this->createSubscription(['delivery_type' => 'digital']);
        $subscriptionIssueFulfilment = SubscriptionIssueFulfilment::create([
            'status' => SubscriptionIssueFulfilmentStatus::SCHEDULED->value,
            'subscription_id' => $subscription->id,
            'issue_delivery_id' => $issueDelivery->id,
            'attempts' => 0,
        ]);

        $deliveryService = Mockery::mock(DeliveryService::class);
        $deliveryService->shouldReceive('registerChannel')->byDefault();
        $deliveryService->shouldReceive('send')->once();
        app()->instance(DeliveryService::class, $deliveryService);

        $job = DeliverIssueDeliveryJob::for(
            $subscriptionIssueFulfilment->id,
            [
                SubscriptionType::DIGITAL->value => app()->make(EmailDeliveryChannel::class),
                SubscriptionType::PRINTED->value => app()->make(PrintDeliveryChannel::class),
            ]
        );
        $job->__wakeup();
        $job->handle();

        $subscriptionIssueFulfilment->refresh();

        $this->assertEquals(SubscriptionIssueFulfilmentStatus::DELIVERED->value, $subscriptionIssueFulfilment->status);
        $this->assertNotNull($subscriptionIssueFulfilment->delivered_at);
    }

    public function test_marks_delivery_as_failed_and_increments_attempts_on_failure(): void
    {
        $issueDelivery = $this->createIssueDelivery();
        $subscription = $this->createSubscription(['delivery_type' => 'digital']);
        $subscriptionIssueFulfilment = SubscriptionIssueFulfilment::create([
            'status' => SubscriptionIssueFulfilmentStatus::SCHEDULED->value,
            'subscription_id' => $subscription->id,
            'issue_delivery_id' => $issueDelivery->id,
            'attempts' => 0,
        ]);

        $deliveryService = Mockery::mock(DeliveryService::class);
        $deliveryService->shouldReceive('registerChannel')->byDefault();
        $deliveryService->shouldReceive('send')->andThrow(new \Exception('Delivery failed'));
        Container::getInstance()->instance(DeliveryService::class, $deliveryService);

        $job = DeliverIssueDeliveryJob::for(
            $subscriptionIssueFulfilment->id,
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

        $subscriptionIssueFulfilment->refresh();

        $this->assertEquals(SubscriptionIssueFulfilmentStatus::FAILED->value, $subscriptionIssueFulfilment->status);
        $this->assertEquals(1, $subscriptionIssueFulfilment->attempts);
        $this->assertStringContainsString('Delivery failed', $subscriptionIssueFulfilment->failure_reason);
    }

    public function test_skips_already_delivered_idempotent(): void
    {
        $issueDelivery = $this->createIssueDelivery();
        $subscription = $this->createSubscription();
        $subscriptionIssueFulfilment = SubscriptionIssueFulfilment::create([
            'status' => SubscriptionIssueFulfilmentStatus::DELIVERED->value,
            'subscription_id' => $subscription->id,
            'issue_delivery_id' => $issueDelivery->id,
            'delivered_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);

        $deliveryService = Mockery::mock(DeliveryService::class);
        $deliveryService->shouldReceive('registerChannel');
        $deliveryService->shouldNotReceive('send');
        Container::getInstance()->instance(DeliveryService::class, $deliveryService);

        $job = DeliverIssueDeliveryJob::for($subscriptionIssueFulfilment->id);
        $job->__wakeup();
        $job->handle();

        $subscriptionIssueFulfilment = SubscriptionIssueFulfilment::find($subscriptionIssueFulfilment->id);
        $this->assertEquals(SubscriptionIssueFulfilmentStatus::DELIVERED->value, $subscriptionIssueFulfilment->status);
    }

    public function test_dispatches_first_issue_event_on_subscribers_first_delivery(): void
    {
        $issueDelivery = $this->createIssueDelivery();
        $subscription = $this->createSubscription(['delivery_type' => 'digital']);
        $subscriptionIssueFulfilment = SubscriptionIssueFulfilment::create([
            'status' => SubscriptionIssueFulfilmentStatus::SCHEDULED->value,
            'subscription_id' => $subscription->id,
            'issue_delivery_id' => $issueDelivery->id,
            'attempts' => 0,
        ]);

        $deliveryService = Mockery::mock(DeliveryService::class);
        $deliveryService->shouldReceive('registerChannel')->byDefault();
        $deliveryService->shouldReceive('send')->once();
        app()->instance(DeliveryService::class, $deliveryService);

        $eventDispatcher = Mockery::mock(EventDispatcher::class);
        $eventDispatcher->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(function ($event) use ($subscription) {
                return $event instanceof SubscriptionFirstIssueDelivered
                    && $event->subscription->id === $subscription->id;
            }));
        app()->instance(EventDispatcher::class, $eventDispatcher);

        $job = DeliverIssueDeliveryJob::for(
            $subscriptionIssueFulfilment->id,
            [
                SubscriptionType::DIGITAL->value => app()->make(EmailDeliveryChannel::class),
                SubscriptionType::PRINTED->value => app()->make(PrintDeliveryChannel::class),
            ]
        );
        $job->__wakeup();
        $job->handle();

        $this->assertTrue(true);
    }

    public function test_does_not_dispatch_first_issue_event_on_second_delivery(): void
    {
        $subscription = $this->createSubscription(['delivery_type' => 'digital']);

        // A prior, already-delivered issue for the same subscription.
        SubscriptionIssueFulfilment::create([
            'status' => SubscriptionIssueFulfilmentStatus::DELIVERED->value,
            'subscription_id' => $subscription->id,
            'issue_delivery_id' => $this->createIssueDelivery()->id,
            'delivered_at' => (new \DateTime('-1 month'))->format('Y-m-d H:i:s'),
        ]);

        $issueDelivery = $this->createIssueDelivery();
        $subscriptionIssueFulfilment = SubscriptionIssueFulfilment::create([
            'status' => SubscriptionIssueFulfilmentStatus::SCHEDULED->value,
            'subscription_id' => $subscription->id,
            'issue_delivery_id' => $issueDelivery->id,
            'attempts' => 0,
        ]);

        $deliveryService = Mockery::mock(DeliveryService::class);
        $deliveryService->shouldReceive('registerChannel')->byDefault();
        $deliveryService->shouldReceive('send')->once();
        app()->instance(DeliveryService::class, $deliveryService);

        $eventDispatcher = Mockery::mock(EventDispatcher::class);
        $eventDispatcher->shouldNotReceive('dispatch')->with(Mockery::type(SubscriptionFirstIssueDelivered::class));
        app()->instance(EventDispatcher::class, $eventDispatcher);

        $job = DeliverIssueDeliveryJob::for(
            $subscriptionIssueFulfilment->id,
            [
                SubscriptionType::DIGITAL->value => app()->make(EmailDeliveryChannel::class),
                SubscriptionType::PRINTED->value => app()->make(PrintDeliveryChannel::class),
            ]
        );
        $job->__wakeup();
        $job->handle();

        $this->assertTrue(true);
    }
}