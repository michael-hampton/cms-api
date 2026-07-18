<?php

declare(strict_types=1);

namespace App\Tests\Unit\Listeners\Subscriptions;

use App\Events\Subscriptions\SubscriptionFirstIssueDelivered;
use App\Framework\Support\Logger;
use App\Listeners\Subscriptions\OnSubscriptionFirstIssueDeliveredSendCommunication;
use App\Models\Subscription;
use App\Services\Subscriptions\Communications\FirstIssueCommunicationDispatchService;
use Mockery;
use PHPUnit\Framework\TestCase;

class OnSubscriptionFirstIssueDeliveredSendCommunicationTest extends TestCase
{
    public function test_dispatches_first_issue_communication(): void
    {
        $dispatcher = Mockery::mock(FirstIssueCommunicationDispatchService::class);
        $logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 100;

        $event = new SubscriptionFirstIssueDelivered($subscription);

        $dispatcher->shouldReceive('dispatch')->once()->with($subscription);

        $listener = new OnSubscriptionFirstIssueDeliveredSendCommunication($dispatcher, $logger);
        $listener->handle($event);

        $this->assertTrue(true);
    }

    public function test_logs_and_swallows_dispatch_failure(): void
    {
        $dispatcher = Mockery::mock(FirstIssueCommunicationDispatchService::class);
        $logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 100;

        $event = new SubscriptionFirstIssueDelivered($subscription);

        $dispatcher->shouldReceive('dispatch')->once()->andThrow(new \RuntimeException('boom'));
        $logger->shouldReceive('error')->once();

        $listener = new OnSubscriptionFirstIssueDeliveredSendCommunication($dispatcher, $logger);
        $listener->handle($event);

        $this->assertTrue(true);
    }
}
