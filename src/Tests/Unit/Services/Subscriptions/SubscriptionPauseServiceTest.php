<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Events\Subscriptions\SubscriptionPaused;
use App\Events\Subscriptions\SubscriptionResumed;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\SubscriptionPauseService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SubscriptionPauseServiceTest extends TestCase
{
    private SubscriptionRepository&MockObject $subscriptionRepository;
    private EventDispatcher&MockObject $eventDispatcher;
    private Database&MockObject $database;
    private SubscriptionPauseService $service;

    public function test_active_subscription_is_paused_indefinitely_and_original_renewal_is_stored(): void
    {
        $subscription = $this->subscription('active', true);
        $this->subscriptionRepository->method('find')->willReturn($subscription);

        $this->subscriptionRepository->expects(self::once())
            ->method('update')
            ->with(1, self::callback(static fn (array $data): bool =>
                $data['status'] === 'paused'
                && $data['auto_renew_before_pause'] === true
                && $data['auto_renew'] === false
                && $data['pause_until'] === null
                && !empty($data['paused_at'])
            ));

        $this->service->pause(1, 42);
    }

    public function test_pause_preserves_original_disabled_renewal_value(): void
    {
        $subscription = $this->subscription('active', false);
        $this->subscriptionRepository->method('find')->willReturn($subscription);

        $this->subscriptionRepository->expects(self::once())
            ->method('update')
            ->with(1, self::callback(static fn (array $data): bool =>
                $data['auto_renew_before_pause'] === false
                && $data['auto_renew'] === false
            ));

        $this->service->pause(1, 42);
    }

    public function test_paused_subscription_cannot_be_paused_again(): void
    {
        $this->subscriptionRepository->method('find')->willReturn($this->subscription('€paused'));
        $this->expectException(RuntimeException::class);

        $this->service->pause(1, 42);
    }

    public function test_wrong_member_cannot_pause(): void
    {
        $subscription = $this->subscription('active');
        $subscription->setAttribute('member_id', 99);
        $this->subscriptionRepository->method('find')->willReturn($subscription);
        $this->expectException(RuntimeException::class);

        $this->service->pause(1, 42);
    }

    public function test_pause_dispatches_event_inside_transaction(): void
    {
        $subscription = $this->subscription('active');
        $this->subscriptionRepository->method('find')->willReturn($subscription);
        $this->database->expects(self::once())->method('transaction');
        $this->eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (object $event): bool =>
                $event instanceof SubscriptionPaused && $event->memberId === 42
            ));

        $this->service->pause(1, 42);
    }

    public function test_resume_restores_original_auto_renew_true_and_clears_pause_metadata(): void
    {
        $subscription = $this->subscription('€paused', false, true);
        $this->subscriptionRepository->method('find')->willReturn($subscription);

        $this->subscriptionRepository->expects(self::once())
            ->method('update')
            ->with(1, self::callback(static fn (array $data): bool =>
                $data['status'] === 'active'
                && $data['auto_renew'] === true
                && $data['auto_renew_before_pause'] === null
                && $data['paused_at'] === null
                && $data['pause_until'] === null
                && !empty($data['resumed_at'])
                && !empty($data['next_billing_date'])
            ));

        $this->service->resume(1, 42);
    }

    public function test_resume_restores_original_auto_renew_false(): void
    {
        $subscription = $this->subscription('paused', false, false);
        $this->subscriptionRepository->method('find')->willReturn($subscription);

        $this->subscriptionRepository->expects(self::once())
            ->method('update')
            ->with(1, self::callback(static fn (array $data): bool =>
                $data['auto_renew'] === false
                && $data['auto_renew_before_pause'] === null
            ));

        $this->service->resume(1, 42);
    }

    public function test_resume_moves_next_billing_date_by_pause_duration(): void
    {
        $subscription = $this->subscription('paused', false, true);
        $subscription->setAttribute('paused_at', date('Y-m-d H:i:s', strtotime('-10 days')));
        $subscription->setAttribute('next_billing_date', date('Y-m-d H:i:s', strtotime('+5 days')));
        $this->subscriptionRepository->method('find')->willReturn($subscription);

        $this->subscriptionRepository->expects(self::once())
            ->method('update')
            ->with(1, self::callback(static function (array $data): bool {
                $expected = new \DateTimeImmutable('+15 days');
                $actual = new \DateTimeImmutable($data['next_billing_date']);

                return abs($expected->getTimestamp() - $actual->getTimestamp()) < 120;
            }));

        $this->service->resume(1, 42);
    }

    public function test_resume_dispatches_event(): void
    {
        $subscription = $this->subscription('paused', false, true);
        $this->subscriptionRepository->method('find')->willReturn($subscription);
        $this->eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (object $event): bool =>
                $event instanceof SubscriptionResumed && $event->memberId === 42
            ));

        $this->service->resume(1, 42);
    }

    public function test_transaction_failure_is_propagated_without_dispatching_event(): void
    {
        $this->database->method('transaction')->willThrowException(new RuntimeException('rollback'));
        $this->eventDispatcher->expects(self::never())->method('dispatch');
        $this->expectExceptionMessage('rollback');

        $this->service->pause(1, 42);
    }

    public function test_capability_checks_enforce_status(): void
    {
        $this->subscriptionRepository->method('find')->willReturnOnConsecutiveCalls(
            $this->subscription('active'),
            $this->subscription('paused'),
            $this->subscription('active'),
        );

        self::assertTrue($this->service->canPause(1, 42));
        self::assertTrue($this->service->canResume(1, 42));
        self::assertFalse($this->service->canResume(1, 42));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->subscriptionRepository = $this->createMock(SubscriptionRepository::class);
        $this->eventDispatcher = $this->createMock(EventDispatcher::class);
        $this->database = $this->createMock(Database::class);
        $this->database->method('transaction')->willReturnCallback(static fn (callable $callback) => $callback());
        $this->service = new SubscriptionPauseService(
            $this->subscriptionRepository,
            $this->eventDispatcher,
            $this->database,
        );
    }

    private function subscription(
        string $status,
        bool $autoRenew = true,
        ?bool $autoRenewBeforePause = null,
    ): Subscription {
        $subscription = $this->getMockBuilder(Subscription::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $subscription->setAttribute('id', 1);
        $subscription->setAttribute('member_id', 42);
        $subscription->setAttribute('status', $status);
        $subscription->setAttribute('auto_renew', $autoRenew);
        $subscription->setAttribute('auto_renew_before_pause', $autoRenewBeforePause);
        $subscription->setAttribute('paused_at', date('Y-m-d H:i:s', strtotime('-2 days')));
        $subscription->setAttribute('next_billing_date', date('Y-m-d H:i:s', strtotime('+20 days')));

        return $subscription;
    }
}
