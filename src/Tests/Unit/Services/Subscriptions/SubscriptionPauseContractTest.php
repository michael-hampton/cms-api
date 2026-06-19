<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\SubscriptionPauseService;
use PHPUnit\Framework\TestCase;

final class SubscriptionPauseContractTest extends TestCase
{
    public function test_pause_preserves_existing_renewal_preference(): void
    {
        $subscription = $this->subscription('active');
        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->method('find')->willReturn($subscription);
        $repository->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(static fn(array $data): bool =>
                $data['status'] === 'paused' && !array_key_exists('auto_renew', $data)
            ));

        $this->service($repository)->pause(1, 42);
    }

    public function test_resume_preserves_existing_renewal_preference(): void
    {
        $subscription = $this->subscription('paused');
        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->method('find')->willReturn($subscription);
        $repository->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(static fn(array $data): bool =>
                $data['status'] === 'active' && !array_key_exists('auto_renew', $data)
            ));

        $this->service($repository)->resume(1, 42);
    }

    public function test_remote_billing_subscription_is_not_locally_pausable(): void
    {
        $subscription = $this->subscription('active');
        $subscription->stripe_subscription_id = 'sub_example';
        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->method('find')->willReturn($subscription);

        $this->assertFalse($this->service($repository)->canPause(1, 42));
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
