<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\SubscriptionPauseService;
use PHPUnit\Framework\TestCase;

final class SubscriptionPauseServiceTest extends TestCase
{
    public function test_active_local_subscription_can_pause(): void
    {
        $subscription = $this->subscription('active');
        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->method('find')->willReturn($subscription);

        $this->assertTrue($this->service($repository)->canPause(1, 42));
    }

    public function test_paused_local_subscription_can_resume(): void
    {
        $subscription = $this->subscription('paused');
        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->method('find')->willReturn($subscription);

        $this->assertTrue($this->service($repository)->canResume(1, 42));
    }

    public function test_wrong_member_cannot_pause(): void
    {
        $subscription = $this->subscription('active');
        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->method('find')->willReturn($subscription);

        $this->assertFalse($this->service($repository)->canPause(1, 99));
    }

    private function service(SubscriptionRepository $repository): SubscriptionPauseService
    {
        $database = $this->createMock(Database::class);
        $database->method('transaction')->willReturnCallback(static fn(callable $callback) => $callback());

        return new SubscriptionPauseService(
            $repository,
            $this->createMock(EventDispatcher::class),
            $database,
        );
    }

    private function subscription(string $status): Subscription
    {
        $subscription = new Subscription();
        $subscription->id = 1;
        $subscription->member_id = 42;
        $subscription->status = $status;
        $subscription->cancel_at_period_end = false;

        return $subscription;
    }
}
