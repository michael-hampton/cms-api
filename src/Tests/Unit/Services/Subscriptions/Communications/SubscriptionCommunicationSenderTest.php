<?php

namespace App\Tests\Unit\Services\Subscriptions\Communications;

use App\Enums\Subscriptions\CommunicationDeliveryStatus;
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

        $this->deliveryRepository = Mockery::mock(SubscriptionCommunicationDeliveryRepository::class);
        $this->notificationDispatcher = Mockery::mock(NotificationDispatcher::class);
        $this->inAppDispatcher = Mockery::mock(InAppNotificationDispatcher::class);
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

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

    public function test_it_skips_duplicate_using_dedupe_key(): void
    {
        $subscription = $this->makeSubscriptionWithMember();
        $communication = $this->makeCommunication(
            channels: ['email'],
            template: FakeLegacySubscriptionCommunicationMail::class,
        );

        $this->deliveryRepository
            ->shouldReceive('hasAlreadySent')
            ->once()
            ->with(100, 1, null, 'pricing-change:77:transition:500:itd')
            ->andReturn(true);

        $this->logger
            ->shouldReceive('info')
            ->once()
            ->with('SubscriptionCommunicationSender: skipping duplicate', Mockery::type('array'));

        $this->deliveryRepository
            ->shouldReceive('recordPending')
            ->never();

        $this->notificationDispatcher
            ->shouldReceive('dispatch')
            ->never();

        $this->sender->send(
            subscription: $subscription,
            communication: $communication,
            schedule: null,
            metadata: [
                'letter_code' => 'ITD_DD_PRICE_RISE',
            ],
            dedupeKey: 'pricing-change:77:transition:500:itd',
        );

        $this->assertTrue(true);
    }

    public function test_it_sends_email_and_records_metadata_and_dedupe_key(): void
    {
        $subscription = $this->makeSubscriptionWithMember();
        $communication = $this->makeCommunication(
            channels: ['email'],
            template: FakeMetadataAwareSubscriptionCommunicationMail::class,
        );

        $delivery = $this->makeDelivery(id: 900, token: 'token-900');

        $this->deliveryRepository
            ->shouldReceive('hasAlreadySent')
            ->once()
            ->with(100, 1, null, 'pricing-change:77:transition:500:itd')
            ->andReturn(false);

        $this->deliveryRepository
            ->shouldReceive('recordPending')
            ->once()
            ->with(
                100,
                200,
                1,
                null,
                'email',
                null,
                null,
                'member@example.com',
                null,
                Mockery::on(function (array $metadata): bool {
                    return $metadata['letter_code'] === 'ITD_DD_PRICE_RISE'
                        && $metadata['pricing_change_id'] === 77;
                }),
                'pricing-change:77:transition:500:itd'
            )
            ->andReturn($delivery);

        $this->notificationDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(function ($notification): bool {
                if (!method_exists($notification, 'toMailable')) {
                    return false;
                }

                $mailable = $notification->toMailable();

                return $mailable instanceof FakeMetadataAwareSubscriptionCommunicationMail
                    && $mailable->deliveryToken === 'token-900'
                    && FakeMetadataAwareSubscriptionCommunicationMail::$lastMetadata['letter_code'] === 'ITD_DD_PRICE_RISE'
                    && FakeMetadataAwareSubscriptionCommunicationMail::$lastMetadata['pricing_change_id'] === 77;
            }))
            ->andReturn(1);

        $this->deliveryRepository
            ->shouldReceive('markSent')
            ->once()
            ->with(900);

        $this->logger
            ->shouldReceive('info')
            ->once()
            ->with('SubscriptionCommunicationSender: email sent', ['delivery_id' => 900]);

        $this->sender->send(
            subscription: $subscription,
            communication: $communication,
            schedule: null,
            metadata: [
                'letter_code' => 'ITD_DD_PRICE_RISE',
                'pricing_change_id' => 77,
            ],
            dedupeKey: 'pricing-change:77:transition:500:itd',
        );

        $this->assertSame('ITD_DD_PRICE_RISE', FakeMetadataAwareSubscriptionCommunicationMail::$lastMetadata['letter_code']);
        $this->assertSame(77, FakeMetadataAwareSubscriptionCommunicationMail::$lastMetadata['pricing_change_id']);
    }

    public function test_it_marks_email_failed_when_mailable_class_does_not_exist(): void
    {
        $subscription = $this->makeSubscriptionWithMember();
        $communication = $this->makeCommunication(
            channels: ['email'],
            template: 'App\\Mail\\DoesNotExist',
        );

        $delivery = $this->makeDelivery(id: 900, token: 'token-900');

        $this->deliveryRepository
            ->shouldReceive('hasAlreadySent')
            ->once()
            ->andReturn(false);

        $this->deliveryRepository
            ->shouldReceive('recordPending')
            ->once()
            ->andReturn($delivery);

        $this->deliveryRepository
            ->shouldReceive('markFailed')
            ->once()
            ->with(900, 'Mailable [App\\Mail\\DoesNotExist] not found.');

        $this->logger
            ->shouldReceive('error')
            ->once()
            ->with('SubscriptionCommunicationSender: mailable not found', Mockery::type('array'));

        $this->notificationDispatcher
            ->shouldReceive('dispatch')
            ->never();

        $this->sender->send($subscription, $communication);

        $this->assertTrue(true);
    }

    public function test_it_marks_email_failed_when_dispatcher_returns_zero(): void
    {
        $subscription = $this->makeSubscriptionWithMember();
        $communication = $this->makeCommunication(
            channels: ['email'],
            template: FakeLegacySubscriptionCommunicationMail::class,
        );

        $delivery = $this->makeDelivery(id: 900, token: 'token-900');

        $this->deliveryRepository
            ->shouldReceive('hasAlreadySent')
            ->once()
            ->andReturn(false);

        $this->deliveryRepository
            ->shouldReceive('recordPending')
            ->once()
            ->andReturn($delivery);

        $this->notificationDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->andReturn(0);

        $this->deliveryRepository
            ->shouldReceive('markFailed')
            ->once()
            ->with(900, 'Dispatcher returned zero successes.');

        $this->sender->send($subscription, $communication);

        $this->assertTrue(true);
    }

    public function test_it_marks_email_failed_when_dispatch_throws(): void
    {
        $subscription = $this->makeSubscriptionWithMember();
        $communication = $this->makeCommunication(
            channels: ['email'],
            template: FakeLegacySubscriptionCommunicationMail::class,
        );

        $delivery = $this->makeDelivery(id: 900, token: 'token-900');

        $this->deliveryRepository
            ->shouldReceive('hasAlreadySent')
            ->once()
            ->andReturn(false);

        $this->deliveryRepository
            ->shouldReceive('recordPending')
            ->once()
            ->andReturn($delivery);

        $this->notificationDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->andThrow(new \RuntimeException('Mail failed'));

        $this->deliveryRepository
            ->shouldReceive('markFailed')
            ->once()
            ->with(900, 'Mail failed');

        $this->logger
            ->shouldReceive('error')
            ->once()
            ->with('SubscriptionCommunicationSender: email dispatch failed', Mockery::type('array'));

        $this->sender->send($subscription, $communication);

        $this->assertTrue(true);
    }

    public function test_it_sends_in_app_and_records_metadata_and_dedupe_key(): void
    {
        $subscription = $this->makeSubscriptionWithMember();
        $communication = $this->makeCommunication(
            channels: ['in_app'],
            template: FakeLegacySubscriptionCommunicationMail::class,
        );

        $delivery = $this->makeDelivery(id: 900, token: 'token-900');

        $this->deliveryRepository
            ->shouldReceive('hasAlreadySent')
            ->once()
            ->with(100, 1, null, 'pricing-change:77:transition:500:itd')
            ->andReturn(false);

        $this->deliveryRepository
            ->shouldReceive('recordPending')
            ->once()
            ->with(
                100,
                200,
                1,
                null,
                'in_app',
                null,
                null,
                null,
                null,
                Mockery::on(function (array $metadata): bool {
                    return $metadata['letter_code'] === 'ITD_DD_PRICE_RISE';
                }),
                'pricing-change:77:transition:500:itd'
            )
            ->andReturn($delivery);

        $this->inAppDispatcher
            ->shouldReceive('dispatchForSubscriptionCommunication')
            ->once()
            ->with(Mockery::type(Member::class), $communication)
            ->andReturn(true);

        $this->deliveryRepository
            ->shouldReceive('markSent')
            ->once()
            ->with(900);

        $this->sender->send(
            subscription: $subscription,
            communication: $communication,
            schedule: null,
            metadata: [
                'letter_code' => 'ITD_DD_PRICE_RISE',
            ],
            dedupeKey: 'pricing-change:77:transition:500:itd',
        );

        $this->assertTrue(true);
    }

    public function test_legacy_four_argument_mailables_still_work(): void
    {
        $subscription = $this->makeSubscriptionWithMember();
        $communication = $this->makeCommunication(
            channels: ['email'],
            template: FakeLegacySubscriptionCommunicationMail::class,
        );

        $delivery = $this->makeDelivery(id: 900, token: 'token-900');

        $this->deliveryRepository
            ->shouldReceive('hasAlreadySent')
            ->once()
            ->andReturn(false);

        $this->deliveryRepository
            ->shouldReceive('recordPending')
            ->once()
            ->andReturn($delivery);

        $this->notificationDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(function ($notification): bool {
                if (!method_exists($notification, 'toMailable')) {
                    return false;
                }

                $mailable = $notification->toMailable();

                return $mailable instanceof FakeLegacySubscriptionCommunicationMail
                    && $mailable->deliveryToken === 'token-900';
            }))
            ->andReturn(1);

        $this->deliveryRepository
            ->shouldReceive('markSent')
            ->once()
            ->with(900);

        $this->logger
            ->shouldReceive('info')
            ->once();

        $this->sender->send($subscription, $communication);

        $this->assertSame(200, FakeLegacySubscriptionCommunicationMail::$lastMemberId);
        $this->assertSame(100, FakeLegacySubscriptionCommunicationMail::$lastSubscriptionId);
        $this->assertSame(1, FakeLegacySubscriptionCommunicationMail::$lastCommunicationId);
    }


    private function makeDelivery(int $id, string $token): SubscriptionCommunicationDelivery
    {
        $delivery = Mockery::mock(SubscriptionCommunicationDelivery::class)->makePartial();

        $delivery->id = $id;
        $delivery->token = $token;
        $delivery->status = CommunicationDeliveryStatus::PENDING->value;

        return $delivery;
    }

    private function makeSubscriptionWithMember(): Subscription
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 200;
        $member->email = 'member@example.com';

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 100;
        $subscription->member = $member;

        return $subscription;
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

use App\Framework\Mail\Mailable;

class FakeLegacySubscriptionCommunicationMail extends Mailable
{
    public ?string $deliveryToken = null;

    public static ?int $lastMemberId = null;
    public static ?int $lastSubscriptionId = null;
    public static ?int $lastCommunicationId = null;

    public function __construct(
        Member $member,
        Subscription $subscription,
        SubscriptionCommunication $communication,
        ?SubscriptionCommunicationSchedule $schedule = null,
    ) {
        parent::__construct();

        self::$lastMemberId = (int) $member->id;
        self::$lastSubscriptionId = (int) $subscription->id;
        self::$lastCommunicationId = (int) $communication->id;

        $this->subject('Fake legacy mail');
    }

    public function build(): Mailable
    {
        return $this;
    }
}

class FakeMetadataAwareSubscriptionCommunicationMail extends Mailable
{
    public ?string $deliveryToken = null;

    public static array $lastMetadata = [];

    public function __construct(
        Member $member,
        Subscription $subscription,
        SubscriptionCommunication $communication,
        ?SubscriptionCommunicationSchedule $schedule = null,
        array $metadata = [],
    ) {
        parent::__construct();

        self::$lastMetadata = $metadata;

        $this->subject('Fake metadata mail');
    }

    public function build(): Mailable
    {
        return $this;
    }
}