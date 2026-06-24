<?php

namespace App\Tests\Unit\Mail\Subscriptions;

use App\Framework\Notifications\MailableNotification;
use App\Framework\Notifications\NotificationDispatcher;
use App\Framework\Support\Logger;
use App\Models\Member;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Notifications\Subscriptions\MailSubscriptionNotificationDispatcher;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class MailSubscriptionNotificationDispatcherTest extends TestCase
{
    private NotificationDispatcher&MockInterface $notificationDispatcher;
    private Logger&MockInterface $logger;
    private MailSubscriptionNotificationDispatcher $dispatcher;

    public function test_it_sends_payment_failed_mailable(): void
    {
        $subscription = $this->makeSubscription();

        $this->notificationDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::type(MailableNotification::class))
            ->andReturn(1);

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

    public function test_it_skips_payment_failed_notification_when_member_has_no_email(): void
    {
        $subscription = $this->makeSubscription(email: null);

        $this->notificationDispatcher->shouldNotReceive('dispatch');
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

        $this->notificationDispatcher->shouldNotReceive('dispatch');

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

        $this->notificationDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::type(MailableNotification::class))
            ->andReturn(1);

        $this->dispatcher->notifySubscriptionCancelled(
            subscription: $subscription,
            accessUntil: new \DateTimeImmutable('2025-07-01'),
        );

        $this->assertTrue(true);
    }

    public function test_it_skips_cancellation_notification_when_member_has_no_email(): void
    {
        $subscription = $this->makeSubscription(email: null);

        $this->notificationDispatcher->shouldNotReceive('dispatch');
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

        $this->notificationDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::type(MailableNotification::class))
            ->andReturn(1);

        $this->dispatcher->notifyPaymentReceived($subscription);

        $this->assertTrue(true);
    }

    public function test_it_skips_payment_received_notification_when_member_has_no_email(): void
    {
        $subscription = $this->makeSubscription(email: null);

        $this->notificationDispatcher->shouldNotReceive('dispatch');
        $this->logger->shouldReceive('warning')->once();

        $this->dispatcher->notifyPaymentReceived($subscription);

        $this->assertTrue(true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->notificationDispatcher = Mockery::mock(NotificationDispatcher::class);
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $this->dispatcher = new MailSubscriptionNotificationDispatcher(
            notificationDispatcher: $this->notificationDispatcher,
            logger: $this->logger,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
