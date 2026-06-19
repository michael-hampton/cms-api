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

final class SubscriptionPauseContractTest extends TestCase
{
    use MocksSubscriptionModels;

    public function test_pause_preserves_existing_renewal_preference(): void
    {
        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->method('find')->willReturn($this->subscription('active'));
        $repository->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(static fn(array $data): bool =>
                $data['status'] === 'paused' && !array_key_exists('auto_renew', $data)
            ));

        $this->service($repository)->pause(1, 42);
    }

    public function test_resume_preserves_existing_renewal_preference(): void
    {
        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->method('find')->willReturn($this->subscription('paused'));
        $repository->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(static fn(array $data): bool =>
                $data['status'] === 'active' && !array_key_exists('auto_renew', $data)
            ));

        $this->service($repository)->resume(1, 42);
    }

    public function test_stripe_subscription_is_pausable_and_calls_gateway(): void
    {
        $subscription = $this->subscription('active', [
            'payment_subscription_id' => 'sub_example',
        ]);
        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->method('find')->willReturn($subscription);
        $repository->method('update');

        $stripe = $this->createMock(StripeSubscriptionLifecycleService::class);
        $stripe->expects($this->once())
            ->method('pause')
            ->with('sub_example')
            ->willReturn(['success' => true, 'status' => 'active']);

        $this->assertTrue($this->service($repository, $stripe)->canPause(1, 42));
        $this->service($repository, $stripe)->pause(1, 42);
    }

    private function service(
        SubscriptionRepository $repository,
        ?StripeSubscriptionLifecycleService $stripe = null,
    ): SubscriptionPauseService {
        $database = $this->createMock(Database::class);
        $database->method('transaction')->willReturnCallback(static fn(callable $callback) => $callback());

        return new SubscriptionPauseService(
            $repository,
            $this->createMock(EventDispatcher::class),
            $database,
            $stripe ?? $this->createMock(StripeSubscriptionLifecycleService::class),
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
