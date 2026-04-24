<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Stripe\StripeSubscriptionDeletedEvent;
use App\Enums\Subscriptions\SubscriptionStatus;
use App\Events\Subscriptions\SubscriptionCancelledByStripe;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Support\Logger;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\SubscriptionCancellationHandler;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class SubscriptionCancellationHandlerTest extends FunctionalTestCase
{
    private EventDispatcher&MockInterface $eventDispatcher;
    private Logger&MockInterface $logger;
    private SubscriptionCancellationHandler $handler;
    private Database $databaseMock;
    private SubscriptionRepository $subscriptionRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventDispatcher = Mockery::mock(EventDispatcher::class);
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();
        $this->databaseMock = Mockery::mock(Database::class);
        $this->subscriptionRepository = Mockery::mock(SubscriptionRepository::class);

        $this->handler = new SubscriptionCancellationHandler(
            subscriptionRepository: $this->subscriptionRepository,
            eventDispatcher: $this->eventDispatcher,
            logger: $this->logger,
            database: $this->databaseMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── Happy path ─────────────────────────────────────────────────────────

    public function test_it_marks_subscription_cancelled_and_sets_cancelled_at(): void
    {
        $cancelledAt = strtotime('2025-06-01 10:00:00');
        $currentPeriodEnd = strtotime('2025-07-01 00:00:00');

        $subscription = $this->makeSubscription('sub_del123', SubscriptionStatus::ACTIVE->value);

        $this->mockTransaction();

        $subscription->shouldReceive('update')
            ->once()
            ->with(Mockery::on(function ($data) use ($cancelledAt, $currentPeriodEnd) {
                return $data['status'] === SubscriptionStatus::CANCELLED->value
                    && $data['auto_renew'] === false
                    && $data['cancelled_at'] === date('Y-m-d H:i:s', $cancelledAt)
                    && $data['end_date'] === date('Y-m-d H:i:s', $currentPeriodEnd);
            }));

        $this->eventDispatcher->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::type(SubscriptionCancelledByStripe::class));

        $event = new StripeSubscriptionDeletedEvent(
            stripeSubscriptionId: 'sub_del123',
            stripeStatus: 'canceled',
            canceledAt: $cancelledAt,
            currentPeriodEnd: $currentPeriodEnd,
        );

        $this->handler->handle($event);
        $this->assertTrue(true);
    }

    public function test_it_wraps_the_update_in_a_transaction(): void
    {
        $subscription = $this->makeSubscription('sub_del123', SubscriptionStatus::ACTIVE->value);
        $transactionHit = false;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function (callable $cb) use (&$transactionHit) {
                $transactionHit = true;
                return $cb();
            });

        $subscription->shouldReceive('update');
        $this->eventDispatcher->shouldReceive('dispatch');

        $this->handler->handle($this->makeEvent('sub_del123'));

        $this->assertTrue($transactionHit, 'Expected Database::transaction() to be called');
    }

    public function test_it_emits_subscription_cancelled_by_stripe_event(): void
    {
        $subscription = $this->makeSubscription('sub_del123', SubscriptionStatus::ACTIVE->value);

        $this->mockTransaction();
        $subscription->shouldReceive('update');

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(function ($dispatched) use ($subscription) {
                return $dispatched instanceof SubscriptionCancelledByStripe
                    && $dispatched->subscription === $subscription
                    && $dispatched->cancelledAt instanceof \DateTimeImmutable
                    && $dispatched->accessUntil instanceof \DateTimeImmutable;
            }));

        $this->handler->handle($this->makeEvent('sub_del123'));
        $this->assertTrue(true);
    }

    // ── Idempotency ────────────────────────────────────────────────────────

    public function test_it_skips_processing_when_subscription_is_already_cancelled(): void
    {
        $subscription = $this->makeSubscription('sub_already', SubscriptionStatus::CANCELLED->value);

        // update() and dispatch() must NOT be called
        $subscription->shouldNotReceive('update');
        $this->eventDispatcher->shouldNotReceive('dispatch');

        $this->handler->handle($this->makeEvent('sub_already'));
        $this->assertTrue(true);
    }

    // ── Unknown subscription ───────────────────────────────────────────────

    public function test_it_logs_a_warning_and_returns_gracefully_when_no_subscription_found(): void
    {
        $this->subscriptionRepository->shouldReceive('findSubscriptionByStripeId')->andReturn(null);

        $this->logger
            ->shouldReceive('warning')
            ->once()
            ->with(
                Mockery::pattern('/no matching subscription/i'),
                Mockery::type('array'),
            );

        $this->eventDispatcher->shouldNotReceive('dispatch');

        $this->handler->handle($this->makeEvent('sub_unknown'));
        $this->assertTrue(true);
    }

    public function test_it_does_not_throw_when_no_subscription_is_found(): void
    {
        $this->subscriptionRepository->shouldReceive('findSubscriptionByStripeId')->andReturn(null);
        $this->logger->shouldReceive('warning');

        // Must complete without exception
        $this->handler->handle($this->makeEvent('sub_ghost'));
        $this->assertTrue(true);
    }

    // ── Factories ──────────────────────────────────────────────────────────

    private function makeSubscription(string $stripeId, string $status): Subscription&MockInterface
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 55;
        $subscription->payment_subscription_id = $stripeId;
        $subscription->status = $status;

        $this->subscriptionRepository->shouldReceive('findSubscriptionByStripeId')->andReturn($subscription);

        return $subscription;
    }

    private function makeEvent(string $stripeSubscriptionId): StripeSubscriptionDeletedEvent
    {
        return new StripeSubscriptionDeletedEvent(
            stripeSubscriptionId: $stripeSubscriptionId,
            stripeStatus: 'canceled',
            canceledAt: strtotime('2025-06-01 10:00:00'),
            currentPeriodEnd: strtotime('2025-07-01 00:00:00'),
        );
    }

    private function mockTransaction(): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn(callable $cb) => $cb());
    }
}