<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\SubscriptionPauseService;
use App\Tests\Support\MocksSubscriptionModels;
use PHPUnit\Framework\TestCase;

final class SubscriptionPauseContractTest extends TestCase
{
    use MocksSubscriptionModels;

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
        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->method('find')->willReturn($this->subscription('active', [
            'payment_subscription_id' => 'sub_example',
        ]));

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
