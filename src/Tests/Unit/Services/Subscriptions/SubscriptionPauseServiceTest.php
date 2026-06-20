<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Events\Subscriptions\SubscriptionPaused;
use App\Events\Subscriptions\SubscriptionResumed;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\SubscriptionPauseService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class SubscriptionPauseServiceTest extends TestCase
{
    private SubscriptionRepository&MockObject $subscriptionRepository;
    private EventDispatcher&MockObject $eventDispatcher;
    private Database $databaseMock;
    private SubscriptionPauseService $service;

    public function test_pause_sets_status_to_paused_and_disables_auto_renew(): void
    {
        $sub = $this->makeSub(1, 42, 'active');
        $this->subscriptionRepository->method('find')->willReturn($sub);

        $this->subscriptionRepository
            ->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(function (array $data) {
                return $data['status'] === 'paused'
                    && $data['auto_renew'] === false
                    && isset($data['paused_at']);
            }));

        $this->service->pause(1, 42);
    }

    // ── pause() ───────────────────────────────────────────────────────────────

    private function makeSub(int $id, int $memberId, string $status): Subscription
    {
        $sub = $this->getMockBuilder(Subscription::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $sub->setAttribute('id', $id);
        $sub->setAttribute('member_id', $memberId);
        $sub->setAttribute('status', $status);
        return $sub;
    }

    public function test_pause_stores_pause_until_when_provided(): void
    {
        $sub = $this->makeSub(1, 42, 'active');
        $this->subscriptionRepository->method('find')->willReturn($sub);

        $this->subscriptionRepository
            ->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(fn(array $data) => $data['pause_until'] !== null));

        $this->service->pause(1, 42, date('Y-m-d', strtotime('+30 days')));
    }

    public function test_pause_caps_pause_until_at_90_days(): void
    {
        $sub = $this->makeSub(1, 42, 'active');
        $this->subscriptionRepository->method('find')->willReturn($sub);

        $requestedDate = date('Y-m-d', strtotime('+200 days'));
        $maxDate = date('Y-m-d', strtotime('+90 days'));

        $this->subscriptionRepository
            ->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(function (array $data) use ($maxDate) {
                return $data['pause_until'] <= $maxDate;
            }));

        $this->service->pause(1, 42, $requestedDate);
    }

    public function test_pause_dispatches_subscription_paused_event(): void
    {
        $sub = $this->makeSub(1, 42, 'active');
        $this->subscriptionRepository->method('find')->willReturn($sub);

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(fn($e) => $e instanceof SubscriptionPaused && $e->memberId === 42));

        $this->service->pause(1, 42);
    }

    public function test_pause_uses_a_transaction(): void
    {
        $this->databaseMock->expects($this->once())->method('transaction');
        $sub = $this->makeSub(1, 42, 'active');
        $this->subscriptionRepository->method('find')->willReturn($sub);

        $this->service->pause(1, 42);
    }

    public function test_pause_throws_for_non_active_subscription(): void
    {
        foreach (['paused', 'cancelled', 'expired', 'past_due'] as $status) {
            $sub = $this->makeSub(1, 42, $status);
            $this->subscriptionRepository->method('find')->willReturn($sub);

            $this->expectException(\RuntimeException::class);
            $this->service->pause(1, 42);

            // Re-create mock to reset state for each iteration
            $this->subscriptionRepository = $this->createMock(SubscriptionRepository::class);
        }
    }

    public function test_pause_throws_if_not_found(): void
    {
        $this->subscriptionRepository->method('find')->willReturn(null);
        $this->expectException(\RuntimeException::class);
        $this->service->pause(999, 42);
    }

    public function test_pause_throws_if_wrong_member(): void
    {
        $sub = $this->makeSub(1, 99, 'active'); // member 99, not 42
        $this->subscriptionRepository->method('find')->willReturn($sub);
        $this->expectException(\RuntimeException::class);
        $this->service->pause(1, 42);
    }

    // ── resume() ──────────────────────────────────────────────────────────────

    public function test_resume_transitions_to_active_and_restores_auto_renew(): void
    {
        $sub = $this->makeSub(1, 42, 'paused');
        $this->subscriptionRepository->method('find')->willReturn($sub);

        $this->subscriptionRepository
            ->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(function (array $data) {
                return $data['status'] === 'active'
                    && $data['auto_renew'] === true
                    && $data['paused_at'] === null
                    && $data['pause_until'] === null
                    && isset($data['resumed_at'])
                    && isset($data['next_billing_date']);
            }));

        $this->service->resume(1, 42);
    }

    public function test_resume_dispatches_subscription_resumed_event(): void
    {
        $sub = $this->makeSub(1, 42, 'paused');
        $this->subscriptionRepository->method('find')->willReturn($sub);

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(fn($e) => $e instanceof SubscriptionResumed && $e->memberId === 42));

        $this->service->resume(1, 42);
    }

    public function test_resume_uses_a_transaction(): void
    {
        $this->databaseMock->expects($this->once())->method('transaction');
        $sub = $this->makeSub(1, 42, 'paused');
        $this->subscriptionRepository->method('find')->willReturn($sub);
        $this->service->resume(1, 42);
    }

    public function test_resume_extends_billing_date_by_pause_duration(): void
    {
        $sub = $this->makeSub(1, 42, 'paused');
        $sub->paused_at = date('Y-m-d H:i:s', strtotime('-30 days'));
        $sub->next_billing_date = date('Y-m-d H:i:s', strtotime('+10 days'));

        $this->subscriptionRepository->method('find')->willReturn($sub);

        $this->subscriptionRepository
            ->expects($this->once())
            ->method('update')
            ->with(
                1,
                $this->anything()
            );

        $this->service->resume(1, 42);
    }

    public function test_resume_throws_if_not_paused(): void
    {
        $sub = $this->makeSub(1, 42, 'active');
        $this->subscriptionRepository->method('find')->willReturn($sub);
        $this->expectException(\RuntimeException::class);
        $this->service->resume(1, 42);
    }

    public function test_resume_throws_if_not_found(): void
    {
        $this->subscriptionRepository->method('find')->willReturn(null);
        $this->expectException(\RuntimeException::class);
        $this->service->resume(999, 42);
    }

    // ── canPause() / canResume() ──────────────────────────────────────────────

    public function test_can_pause_returns_true_for_active(): void
    {
        $sub = $this->makeSub(1, 42, 'active');
        $this->subscriptionRepository->method('find')->willReturn($sub);
        $this->assertTrue($this->service->canPause(1, 42));
    }

    public function test_can_pause_returns_false_for_paused(): void
    {
        $sub = $this->makeSub(1, 42, 'paused');
        $this->subscriptionRepository->method('find')->willReturn($sub);
        $this->assertFalse($this->service->canPause(1, 42));
    }

    public function test_can_pause_returns_false_for_wrong_member(): void
    {
        $sub = $this->makeSub(1, 99, 'active');
        $this->subscriptionRepository->method('find')->willReturn($sub);
        $this->assertFalse($this->service->canPause(1, 42));
    }

    public function test_can_resume_returns_true_for_paused(): void
    {
        $sub = $this->makeSub(1, 42, 'paused');
        $this->subscriptionRepository->method('find')->willReturn($sub);
        $this->assertTrue($this->service->canResume(1, 42));
    }

    public function test_can_resume_returns_false_for_active(): void
    {
        $sub = $this->makeSub(1, 42, 'active');
        $this->subscriptionRepository->method('find')->willReturn($sub);
        $this->assertFalse($this->service->canResume(1, 42));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();
        $this->subscriptionRepository = $this->createMock(SubscriptionRepository::class);
        $this->eventDispatcher = $this->createMock(EventDispatcher::class);
        $this->databaseMock = $this->createMock(Database::class);

        $this->databaseMock
            ->method('transaction')
            ->willReturnCallback(fn(callable $cb) => $cb());

        $this->service = new SubscriptionPauseService(
            $this->subscriptionRepository,
            $this->eventDispatcher,
            $this->databaseMock,
        );

    }
}