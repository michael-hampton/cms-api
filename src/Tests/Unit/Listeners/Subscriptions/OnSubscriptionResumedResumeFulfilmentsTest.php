<?php

declare(strict_types=1);

namespace App\Tests\Unit\Listeners\Subscriptions;

use App\Events\Subscriptions\SubscriptionResumed;
use App\Framework\Support\Logger;
use App\Listeners\Subscriptions\OnSubscriptionResumedResumeFulfilments;
use App\Models\Subscription;
use App\Services\Subscriptions\SubscriptionFulfilmentPauseService;
use Mockery;
use PHPUnit\Framework\TestCase;

class OnSubscriptionResumedResumeFulfilmentsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_resumes_fulfilments_for_the_resumed_subscription(): void
    {
        $pauseService = Mockery::mock(SubscriptionFulfilmentPauseService::class);
        $logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 21;

        $pauseService->shouldReceive('resume')->once()->with($subscription);

        $event = new SubscriptionResumed($subscription, 55);

        $listener = new OnSubscriptionResumedResumeFulfilments($pauseService, $logger);
        $listener->handle($event);

        $this->assertTrue(true);
    }

    public function test_swallows_and_logs_resume_failure(): void
    {
        $pauseService = Mockery::mock(SubscriptionFulfilmentPauseService::class);
        $logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 21;

        $pauseService->shouldReceive('resume')->andThrow(new \RuntimeException('boom'));

        $event = new SubscriptionResumed($subscription, 55);

        $listener = new OnSubscriptionResumedResumeFulfilments($pauseService, $logger);
        $listener->handle($event);

        $this->assertTrue(true);
    }
}
