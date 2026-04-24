<?php

namespace App\Tests\Unit\Mail\Subscriptions;

use App\Framework\Mail\MailManager;
use App\Framework\Support\Logger;
use App\Mail\Subscriptions\PaymentFailedMailable;
use App\Mail\Subscriptions\PaymentReceivedMailable;
use App\Mail\Subscriptions\SubscriptionCancelledMailable;
use App\Models\Member;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Notifications\Subscriptions\MailSubscriptionNotificationDispatcher;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class MailSubscriptionNotificationDispatcherTest extends TestCase
{
    private MailManager&MockInterface $mailManager;
    private Logger&MockInterface $logger;
    private MailSubscriptionNotificationDispatcher $dispatcher;

    public function test_it_sends_payment_failed_mailable(): void
    {
        $subscription = $this->makeSubscription();

        $this->mailManager
            ->shouldReceive('send')
            ->once()
            ->with(Mockery::type(PaymentFailedMailable::class));

        $this->dispatcher->notifyPaymentFailed(
            subscription: $subscription,
            gracePeriodUntil: new \DateTimeImmutable('2025-06-08'),
            failureReason: 'card_declined',
        );
        $this->assertTrue(true);
    }

    private function makeSubscription(?string $email = 'member@example.com'): Subscription
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $member->email = $email;
        $member->full_name = 'Jane Smith';

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 5;
        $plan->name = 'Premium Monthly';

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 10;
        $subscription->price = 29.00;
        $subscription->currency = 'GBP';
        $subscription->plan_name = 'Premium Monthly';
        $subscription->current_period_end = new \DateTime('2025-07-01');
        $subscription->member = $member;
        $subscription->plan = $plan;

        return $subscription;
    }

    // ── notifyPaymentFailed ────────────────────────────────────────────────

    public function test_it_skips_payment_failed_notification_when_member_has_no_email(): void
    {
        $subscription = $this->makeSubscription(email: null);

        $this->mailManager->shouldNotReceive('send');
        $this->logger->shouldReceive('warning')->once();

        $this->dispatcher->notifyPaymentFailed(
            subscription: $subscription,
            gracePeriodUntil: new \DateTimeImmutable('2025-06-08'),
            failureReason: null,
        );
        $this->assertTrue(true);
    }

    public function test_it_skips_payment_failed_notification_when_email_is_invalid(): void
    {
        $subscription = $this->makeSubscription(email: 'not-an-email');

        $this->mailManager->shouldNotReceive('send');

        $this->dispatcher->notifyPaymentFailed(
            subscription: $subscription,
            gracePeriodUntil: new \DateTimeImmutable('2025-06-08'),
            failureReason: null,
        );
        $this->assertTrue(true);
    }

    public function test_it_sends_subscription_cancelled_mailable(): void
    {
        $subscription = $this->makeSubscription();

        $this->mailManager
            ->shouldReceive('send')
            ->once()
            ->with(Mockery::type(SubscriptionCancelledMailable::class));

        $this->dispatcher->notifySubscriptionCancelled(
            subscription: $subscription,
            accessUntil: new \DateTimeImmutable('2025-07-01'),
        );
        $this->assertTrue(true);
    }

    // ── notifySubscriptionCancelled ────────────────────────────────────────

    public function test_it_skips_cancellation_notification_when_member_has_no_email(): void
    {
        $subscription = $this->makeSubscription(email: null);

        $this->mailManager->shouldNotReceive('send');
        $this->logger->shouldReceive('warning')->once();

        $this->dispatcher->notifySubscriptionCancelled(
            subscription: $subscription,
            accessUntil: new \DateTimeImmutable('2025-07-01'),
        );
        $this->assertTrue(true);
    }

    public function test_it_sends_payment_received_mailable(): void
    {
        $subscription = $this->makeSubscription();

        $this->mailManager
            ->shouldReceive('send')
            ->once()
            ->with(Mockery::type(PaymentReceivedMailable::class));

        $this->dispatcher->notifyPaymentReceived($subscription);
        $this->assertTrue(true);
    }

    // ── notifyPaymentReceived ──────────────────────────────────────────────

    public function test_it_skips_payment_received_notification_when_member_has_no_email(): void
    {
        $subscription = $this->makeSubscription(email: null);

        $this->mailManager->shouldNotReceive('send');
        $this->logger->shouldReceive('warning')->once();

        $this->dispatcher->notifyPaymentReceived($subscription);
        $this->assertTrue(true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailManager = Mockery::mock(MailManager::class);
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $this->dispatcher = new MailSubscriptionNotificationDispatcher(
            mailManager: $this->mailManager,
            logger: $this->logger,
        );
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}