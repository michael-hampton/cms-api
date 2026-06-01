<?php

namespace App\Tests\Unit\Services\Subscriptions\Communications;

use App\Framework\Notifications\NotificationDispatcher;
use App\Framework\Support\Logger;
use App\Models\Member;
use App\Models\Subscription;
use App\Models\SubscriptionCommunication;
use App\Models\SubscriptionCommunicationDelivery;
use App\Models\SubscriptionCommunicationSchedule;
use App\Repositories\Subscriptions\SubscriptionCommunicationDeliveryRepository;
use App\Services\MemberInsights\InAppNotificationDispatcher;
use App\Services\Subscriptions\Communications\SubscriptionCommunicationSender;
use Mockery;
use PHPUnit\Framework\TestCase;

class SubscriptionCommunicationSenderTest extends TestCase
{
    private SubscriptionCommunicationDeliveryRepository $deliveryRepository;
    private NotificationDispatcher                      $notificationDispatcher;
    private InAppNotificationDispatcher                 $inAppDispatcher;
    private Logger                                      $logger;
    private SubscriptionCommunicationSender             $sender;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deliveryRepository      = Mockery::mock(SubscriptionCommunicationDeliveryRepository::class);
        $this->notificationDispatcher  = Mockery::mock(NotificationDispatcher::class);
        $this->inAppDispatcher         = Mockery::mock(InAppNotificationDispatcher::class);
        $this->logger                  = Mockery::mock(Logger::class);

        $this->sender = new SubscriptionCommunicationSender(
            $this->deliveryRepository,
            $this->notificationDispatcher,
            $this->inAppDispatcher,
            $this->logger,
        );
    }

    public function test_email_send_creates_delivery_and_marks_sent(): void
    {
        $member   = $this->makeMember(1, 'test@example.com');
        $sub      = $this->makeSubscription(1, $member);
        $schedule = Mockery::mock(SubscriptionCommunicationSchedule::class)->makePartial();
        $schedule->id = 5;

        $comm = $this->makeCommunication(
            channels: ['email'],
            template: \App\Mail\Subscriptions\RenewalReminderMail::class,
        );

        $delivery = Mockery::mock(SubscriptionCommunicationDelivery::class)->makePartial();
        $delivery->id    = 99;
        $delivery->token = 'test-token-uuid';

        $this->deliveryRepository->shouldReceive('hasAlreadySent')
            ->once()->andReturn(false);

        $this->deliveryRepository->shouldReceive('recordPending')
            ->once()->andReturn($delivery);

        $this->notificationDispatcher->shouldReceive('dispatch')
            ->once()->andReturn(1);

        $this->deliveryRepository->shouldReceive('markSent')
            ->once()->with(99);

        $this->logger->shouldReceive('info')->once();

        $this->sender->send($sub, $comm, $schedule);

        $this->assertTrue(true);
    }

    public function test_email_send_marks_failed_when_dispatcher_returns_zero(): void
    {
        $member = $this->makeMember(1, 'test@example.com');
        $sub    = $this->makeSubscription(1, $member);
        $comm   = $this->makeCommunication(
            channels: ['email'],
            template: \App\Mail\Subscriptions\RenewalReminderMail::class,
        );

        $delivery = Mockery::mock(SubscriptionCommunicationDelivery::class)->makePartial();
        $delivery->id    = 10;
        $delivery->token = 'token';

        $this->deliveryRepository->shouldReceive('hasAlreadySent')->andReturn(false);
        $this->deliveryRepository->shouldReceive('recordPending')->andReturn($delivery);

        $this->notificationDispatcher->shouldReceive('dispatch')->andReturn(0);

        $this->deliveryRepository->shouldReceive('markFailed')
            ->once()
            ->with(10, Mockery::type('string'));

        $this->logger->shouldReceive('info', 'error', 'warning')->zeroOrMoreTimes();

        $this->sender->send($sub, $comm);

        $this->assertTrue(true);
    }

    public function test_delivery_token_is_set_on_mailable_before_dispatch(): void
    {
        $member = $this->makeMember(1, 'test@example.com');
        $sub    = $this->makeSubscription(1, $member);
        $comm   = $this->makeCommunication(
            channels: ['email'],
            template: \App\Mail\Subscriptions\RenewalReminderMail::class,
        );

        $delivery = Mockery::mock(SubscriptionCommunicationDelivery::class)->makePartial();
        $delivery->id    = 12;
        $delivery->token = 'expected-token';

        $capturedMailable = null;

        $this->deliveryRepository->shouldReceive('hasAlreadySent')->andReturn(false);
        $this->deliveryRepository->shouldReceive('recordPending')->andReturn($delivery);
        $this->deliveryRepository->shouldReceive('markSent')->once();

        $this->notificationDispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(function ($notification) use (&$capturedMailable) {
                $capturedMailable = $notification->toMailable();
                return true;
            })
            ->andReturn(1);

        $this->logger->shouldReceive('info')->once();

        $this->sender->send($sub, $comm);

        $this->assertNotNull($capturedMailable);
        $this->assertSame('expected-token', $capturedMailable->deliveryToken);
    }

    public function test_in_app_send_creates_delivery_and_marks_sent(): void
    {
        $member = $this->makeMember(1, 'test@example.com');
        $sub    = $this->makeSubscription(1, $member);
        $comm   = $this->makeCommunication(channels: ['in_app'], template: '');

        $delivery = Mockery::mock(SubscriptionCommunicationDelivery::class)->makePartial();
        $delivery->id    = 20;
        $delivery->token = null;

        $this->deliveryRepository->shouldReceive('hasAlreadySent')->andReturn(false);
        $this->deliveryRepository->shouldReceive('recordPending')->andReturn($delivery);

        $this->inAppDispatcher->shouldReceive('dispatchForSubscriptionCommunication')
            ->once()->andReturn(true);

        $this->deliveryRepository->shouldReceive('markSent')->once()->with(20);

        $this->logger->shouldReceive('info', 'warning', 'error')->zeroOrMoreTimes();

        $this->sender->send($sub, $comm);

        $this->assertTrue(true);
    }

    public function test_skips_send_when_already_delivered(): void
    {
        $member = $this->makeMember(1, 'test@example.com');
        $sub    = $this->makeSubscription(1, $member);
        $comm   = $this->makeCommunication(channels: ['email'], template: '');

        $this->deliveryRepository->shouldReceive('hasAlreadySent')->andReturn(true);
        $this->deliveryRepository->shouldReceive('recordPending')->never();
        $this->notificationDispatcher->shouldReceive('dispatch')->never();

        $this->logger->shouldReceive('info')->once();

        $this->sender->send($sub, $comm);

        $this->assertTrue(true);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function makeMember(int $id, string $email): Member
    {
        $member        = Mockery::mock(Member::class)->makePartial();
        $member->id    = $id;
        $member->email = $email;
        return $member;
    }

    private function makeSubscription(int $id, Member $member): Subscription
    {
        $sub         = Mockery::mock(Subscription::class)->makePartial();
        $sub->id     = $id;
        $sub->member = $member;
        return $sub;
    }

    private function makeCommunication(array $channels, string $template): SubscriptionCommunication
    {
        $comm           = Mockery::mock(SubscriptionCommunication::class)->makePartial();
        $comm->id       = 1;
        $comm->channels = $channels;
        $comm->template = $template;
        return $comm;
    }
}