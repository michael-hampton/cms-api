<?php

declare(strict_types=1);

namespace App\Tests\Unit\Listeners\Subscriptions;

use App\Events\Subscriptions\SubscriptionPaused;
use App\Framework\Support\Logger;
use App\Listeners\Subscriptions\OnSubscriptionPausedPauseFulfilments;
use App\Models\Subscription;
use App\Services\Subscriptions\SubscriptionFulfilmentPauseService;
use Mockery;
use PHPUnit\Framework\TestCase;

class OnSubscriptionPausedPauseFulfilmentsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_pauses_fulfilments_for_the_paused_subscription(): void
    {
        $pauseService = Mockery::mock(SubscriptionFulfilmentPauseService::class);
        $logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 21;

        $pauseService->shouldReceive('pause')->once()->with($subscription);

        $event = new SubscriptionPaused($subscription, '2026-08-20', 55);

        $listener = new OnSubscriptionPausedPauseFulfilments($pauseService, $logger);
        $listener->handle($event);

        $this->assertTrue(true);
    }

    public function test_swallows_and_logs_pause_failure(): void
    {
        $pauseService = Mockery::mock(SubscriptionFulfilmentPauseService::class);
        $logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 21;

        $pauseService->shouldReceive('pause')->andThrow(new \RuntimeException('boom'));

        $event = new SubscriptionPaused($subscription, '2026-08-20', 55);

        $listener = new OnSubscriptionPausedPauseFulfilments($pauseService, $logger);
        $listener->handle($event);

        $this->assertTrue(true);
    }
}
