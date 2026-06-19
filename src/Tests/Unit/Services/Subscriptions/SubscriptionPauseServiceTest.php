<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Events\Subscriptions\SubscriptionPaused;
use App\Events\Subscriptions\SubscriptionResumed;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\Stripe\StripeSubscriptionLifecycleService;
use App\Services\Subscriptions\SubscriptionPauseService;
use App\Tests\Support\MocksSubscriptionModels;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SubscriptionPauseServiceTest extends TestCase
{
    use MocksSubscriptionModels;

    private SubscriptionRepository&MockObject $repository;
    private EventDispatcher&MockObject $events;
    private Database&MockObject $database;
    private StripeSubscriptionLifecycleService&MockObject $stripe;
    private SubscriptionPauseService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createMock(SubscriptionRepository::class);
        $this->events = $this->createMock(EventDispatcher::class);
        $this->database = $this->createMock(Database::class);
        $this->stripe = $this->createMock(StripeSubscriptionLifecycleService::class);

        $this->database
            ->method('transaction')
            ->willReturnCallback(static fn(callable $callback) => $callback());

        $this->service = new SubscriptionPauseService(
            $this->repository,
            $this->events,
            $this->database,
            $this->stripe,
        );
    }

    public function test_pause_updates_status_without_changing_auto_renew(): void
    {
        $subscription = $this->subscription('active', ['auto_renew' => false]);
        $this->repository->method('find')->willReturn($subscription);

        $this->repository
            ->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(
                static fn(array $data): bool =>
                    $data['status'] === 'paused'
                    && isset($data['paused_at'])
                    && !array_key_exists('auto_renew', $data)
            ));

        $this->service->pause(1, 42);
    }

    public function test_pause_stores_requested_date(): void
    {
        $this->repository->method('find')->willReturn($this->subscription('active'));
        $pauseUntil = date('Y-m-d', strtotime('+30 days'));

        $this->repository
            ->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(
                static fn(array $data): bool => $data['pause_until'] === $pauseUntil
            ));

        $this->service->pause(1, 42, $pauseUntil);
    }

    public function test_pause_caps_requested_date_at_90_days(): void
    {
        $this->repository->method('find')->willReturn($this->subscription('active'));
        $maximum = date('Y-m-d', strtotime('+90 days'));

        $this->repository
            ->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(
                static fn(array $data): bool => $data['pause_until'] <= $maximum
            ));

        $this->service->pause(1, 42, date('Y-m-d', strtotime('+200 days')));
    }

    public function test_pause_rejects_past_date(): void
    {
        $this->repository->method('find')->willReturn($this->subscription('active'));
        $this->repository->expects($this->never())->method('update');

        $this->expectException(RuntimeException::class);

        $this->service->pause(1, 42, date('Y-m-d', strtotime('-1 day')));
    }

    public function test_pause_uses_transaction_and_dispatches_event(): void
    {
        $this->repository->method('find')->willReturn($this->subscription('active'));

        $this->database
            ->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(static fn(callable $callback) => $callback());

        $this->events
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(SubscriptionPaused::class));

        $this->service->pause(1, 42);
    }

    /** @dataProvider nonPausableStatuses */
    public function test_pause_rejects_non_active_statuses(string $status): void
    {
        $this->repository->method('find')->willReturn($this->subscription($status));

        $this->expectException(RuntimeException::class);

        $this->service->pause(1, 42);
    }

    public static function nonPausableStatuses(): array
    {
        return [
            ['paused'],
            ['cancelled'],
            ['expired'],
            ['past_due'],
        ];
    }

    public function test_pause_rejects_missing_subscription(): void
    {
        $this->repository->method('find')->willReturn(null);

        $this->expectException(RuntimeException::class);

        $this->service->pause(999, 42);
    }

    public function test_pause_rejects_wrong_member(): void
    {
        $this->repository
            ->method('find')
            ->willReturn($this->subscription('active', ['member_id' => 99]));

        $this->expectException(RuntimeException::class);

        $this->service->pause(1, 42);
    }

    public function test_pause_calls_stripe_before_local_update(): void
    {
        $subscription = $this->subscription(
            'active',
            ['payment_subscription_id' => 'sub_123']
        );
        $this->repository->method('find')->willReturn($subscription);

        $this->stripe
            ->expects($this->once())
            ->method('pause')
            ->with('sub_123')
            ->willReturn([
                'success' => true,
                'current_period_end' => strtotime('+1 month'),
            ]);

        $this->repository->expects($this->once())->method('update');

        $this->service->pause(1, 42);
    }

    public function test_pause_does_not_update_locally_when_stripe_fails(): void
    {
        $subscription = $this->subscription(
            'active',
            ['payment_subscription_id' => 'sub_123']
        );
        $this->repository->method('find')->willReturn($subscription);

        $this->stripe
            ->method('pause')
            ->willReturn(['success' => false, 'message' => 'Stripe failed']);

        $this->repository->expects($this->never())->method('update');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Stripe failed');

        $this->service->pause(1, 42);
    }

    public function test_pause_compensates_stripe_when_local_transaction_fails(): void
    {
        $subscription = $this->subscription(
            'active',
            ['payment_subscription_id' => 'sub_123']
        );
        $this->repository->method('find')->willReturn($subscription);

        $this->stripe
            ->expects($this->once())
            ->method('pause')
            ->with('sub_123')
            ->willReturn(['success' => true]);

        $this->database
            ->expects($this->once())
            ->method('transaction')
            ->willThrowException(new RuntimeException('Database failed'));

        $this->stripe
            ->expects($this->once())
            ->method('resume')
            ->with('sub_123')
            ->willReturn(['success' => true]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Database failed');

        $this->service->pause(1, 42);
    }

    public function test_resume_updates_status_without_changing_auto_renew(): void
    {
        $subscription = $this->subscription('paused', [
            'auto_renew' => false,
            'paused_at' => date('Y-m-d H:i:s', strtotime('-10 days')),
            'next_billing_date' => date('Y-m-d H:i:s', strtotime('+20 days')),
        ]);
        $this->repository->method('find')->willReturn($subscription);

        $this->repository
            ->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(
                static fn(array $data): bool =>
                    $data['status'] === 'active'
                    && $data['paused_at'] === null
                    && $data['pause_until'] === null
                    && isset($data['next_billing_date'], $data['resumed_at'])
                    && !array_key_exists('auto_renew', $data)
            ));

        $this->service->resume(1, 42);
    }

    public function test_resume_uses_transaction_and_dispatches_event(): void
    {
        $this->repository->method('find')->willReturn($this->subscription('paused'));

        $this->database
            ->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(static fn(callable $callback) => $callback());

        $this->events
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(SubscriptionResumed::class));

        $this->service->resume(1, 42);
    }

    public function test_resume_rejects_missing_subscription(): void
    {
        $this->repository->method('find')->willReturn(null);

        $this->expectException(RuntimeException::class);

        $this->service->resume(999, 42);
    }

    public function test_resume_rejects_wrong_member(): void
    {
        $this->repository
            ->method('find')
            ->willReturn($this->subscription('paused', ['member_id' => 99]));

        $this->expectException(RuntimeException::class);

        $this->service->resume(1, 42);
    }

    public function test_resume_rejects_non_paused_subscription(): void
    {
        $this->repository->method('find')->willReturn($this->subscription('active'));

        $this->expectException(RuntimeException::class);

        $this->service->resume(1, 42);
    }

    public function test_local_resume_accepts_string_billing_date_and_extends_it(): void
    {
        $subscription = $this->subscription('paused', [
            'paused_at' => date('Y-m-d H:i:s', strtotime('-10 days')),
            'next_billing_date' => date('Y-m-d H:i:s', strtotime('+20 days')),
        ]);
        $this->repository->method('find')->willReturn($subscription);

        $expected = date('Y-m-d', strtotime('+30 days'));

        $this->repository
            ->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(
                static fn(array $data): bool =>
                str_starts_with($data['next_billing_date'], $expected)
            ));

        $this->service->resume(1, 42);
    }

    public function test_stripe_resume_uses_stripe_current_period_end(): void
    {
        $periodEnd = strtotime('+45 days');
        $subscription = $this->subscription('paused', [
            'payment_subscription_id' => 'sub_123',
            'next_billing_date' => date('Y-m-d H:i:s', strtotime('+10 days')),
        ]);
        $this->repository->method('find')->willReturn($subscription);

        $this->stripe
            ->expects($this->once())
            ->method('resume')
            ->with('sub_123')
            ->willReturn([
                'success' => true,
                'current_period_end' => $periodEnd,
            ]);

        $expected = date('Y-m-d', $periodEnd);

        $this->repository
            ->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(
                static fn(array $data): bool =>
                str_starts_with($data['next_billing_date'], $expected)
            ));

        $this->service->resume(1, 42);
    }

    public function test_resume_does_not_update_locally_when_stripe_fails(): void
    {
        $subscription = $this->subscription(
            'paused',
            ['payment_subscription_id' => 'sub_123']
        );
        $this->repository->method('find')->willReturn($subscription);

        $this->stripe
            ->method('resume')
            ->willReturn(['success' => false, 'message' => 'Stripe failed']);

        $this->repository->expects($this->never())->method('update');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Stripe failed');

        $this->service->resume(1, 42);
    }

    public function test_resume_compensates_stripe_when_local_transaction_fails(): void
    {
        $subscription = $this->subscription('paused', [
            'payment_subscription_id' => 'sub_123',
            'next_billing_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
        ]);
        $this->repository->method('find')->willReturn($subscription);

        $this->stripe
            ->expects($this->once())
            ->method('resume')
            ->with('sub_123')
            ->willReturn([
                'success' => true,
                'current_period_end' => strtotime('+1 month'),
            ]);

        $this->database
            ->expects($this->once())
            ->method('transaction')
            ->willThrowException(new RuntimeException('Database failed'));

        $this->stripe
            ->expects($this->once())
            ->method('pause')
            ->with('sub_123')
            ->willReturn(['success' => true]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Database failed');

        $this->service->resume(1, 42);
    }

    public function test_can_pause_public_wrapper_handles_missing_wrong_owner_and_status(): void
    {
        $this->repository->method('find')->willReturnOnConsecutiveCalls(
            null,
            $this->subscription('active', ['member_id' => 99]),
            $this->subscription('paused'),
            $this->subscription('active')
        );

        $this->assertFalse($this->service->canPause(1, 42));
        $this->assertFalse($this->service->canPause(1, 42));
        $this->assertFalse($this->service->canPause(1, 42));
        $this->assertTrue($this->service->canPause(1, 42));
    }

    public function test_can_resume_public_wrapper_handles_missing_wrong_owner_and_status(): void
    {
        $this->repository->method('find')->willReturnOnConsecutiveCalls(
            null,
            $this->subscription('paused', ['member_id' => 99]),
            $this->subscription('active'),
            $this->subscription('paused')
        );

        $this->assertFalse($this->service->canResume(1, 42));
        $this->assertFalse($this->service->canResume(1, 42));
        $this->assertFalse($this->service->canResume(1, 42));
        $this->assertTrue($this->service->canResume(1, 42));
    }

    public function test_scheduled_cancellation_cannot_be_paused(): void
    {
        $scheduled = $this->subscription(
            'active',
            [],
            ['isCancellationScheduled' => true]
        );

        $this->assertFalse(
            $this->service->canPauseSubscription($scheduled, 42)
        );
    }

    private function subscription(
        string $status,
        array $attributes = [],
        array $methods = []
    ): Subscription {
        return $this->mockSubscription(
            array_merge([
                'id' => 1,
                'member_id' => 42,
                'status' => $status,
                'cancel_at_period_end' => false,
                'paused_at' => null,
                'pause_until' => null,
                'next_billing_date' => null,
                'payment_subscription_id' => null,
            ], $attributes),
            array_merge([
                'isCancellationScheduled' => false,
            ], $methods)
        );
    }
}
