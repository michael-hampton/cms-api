<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\Stripe\StripeSubscriptionLifecycleService;
use App\Services\Subscriptions\SubscriptionPauseService;
use App\Tests\Support\MocksSubscriptionModels;
use PHPUnit\Framework\TestCase;

final class SubscriptionPauseServiceTest extends TestCase
{
    use MocksSubscriptionModels;

    public function test_active_subscription_can_pause(): void
    {
        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->method('find')->willReturn($this->subscription('active'));

        $this->assertTrue($this->service($repository)->canPause(1, 42));
    }

    public function test_paused_subscription_can_resume(): void
    {
        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->method('find')->willReturn($this->subscription('paused'));

        $this->assertTrue($this->service($repository)->canResume(1, 42));
    }

    public function test_wrong_member_cannot_pause(): void
    {
        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->method('find')->willReturn($this->subscription('active'));

        $this->assertFalse($this->service($repository)->canPause(1, 99));
    }

    public function test_stripe_subscription_can_pause(): void
    {
        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->method('find')->willReturn($this->subscription('active', [
            'payment_subscription_id' => 'sub_example',
        ]));

        $this->assertTrue($this->service($repository)->canPause(1, 42));
    }

    private function service(SubscriptionRepository $repository): SubscriptionPauseService
    {
        $database = $this->createMock(Database::class);
        $database->method('transaction')->willReturnCallback(static fn(callable $callback) => $callback());

        return new SubscriptionPauseService(
            $repository,
            $this->createMock(EventDispatcher::class),
            $database,
            $this->createMock(StripeSubscriptionLifecycleService::class),
        );
    }

    private function subscription(string $status, array $attributes = []): Subscription
    {
        return $this->mockSubscription(array_merge([
            'id' => 1,
            'member_id' => 42,
            'status' => $status,
            'cancel_at_period_end' => false,
        ], $attributes), [
            'isCancellationScheduled' => false,
        ]);
    }
}
