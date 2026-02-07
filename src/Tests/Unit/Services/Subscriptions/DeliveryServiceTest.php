<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Services\Subscriptions\DeliveryChannelInterface;
use App\Services\Subscriptions\DeliveryService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery;

class DeliveryServiceTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_sends_to_registered_channel(): void
    {
        $service = new DeliveryService();

        $channel = Mockery::mock(DeliveryChannelInterface::class);
        $channel->shouldReceive('send')->once();

        $service->registerChannel('digital', $channel);

        $subscription = $this->createSubscription(['delivery_type' => 'digital']);
        $issueDelivery = $this->createIssueDelivery();

        $service->send($subscription, $issueDelivery);

        $this->assertTrue(true);
    }

    public function test_throws_exception_for_unregistered_channel(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No delivery channel registered for type: digital');

        $service = new DeliveryService();

        $subscription = $this->createSubscription(['delivery_type' => 'digital']);
        $issueDelivery = $this->createIssueDelivery();

        $service->send($subscription, $issueDelivery);
    }
}