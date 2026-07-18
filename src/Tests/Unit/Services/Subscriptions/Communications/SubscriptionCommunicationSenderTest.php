<?php

namespace App\Tests\Unit\Services\Subscriptions\Communications;

use App\Enums\Subscriptions\CommunicationDeliveryStatus;
use App\Framework\Database\Database;
use App\Framework\Notifications\NotificationDispatcher;
use App\Framework\Support\Logger;
use App\Models\Member;
use App\Models\Subscription;
use App\Models\SubscriptionCommunication;
use App\Models\SubscriptionCommunicationDelivery;
use App\Models\SubscriptionCommunicationLetterFulfilment;
use App\Models\SubscriptionCommunicationSchedule;
use App\Repositories\Subscriptions\SubscriptionCommunicationDeliveryRepository;
use App\Repositories\Subscriptions\SubscriptionCommunicationLetterRepository;
use App\Repositories\Subscriptions\SubscriptionCommunicationSuppressionLogRepository;
use App\Services\MemberInsights\InAppNotificationDispatcher;
use App\Services\Subscriptions\Communications\CommunicationChannelResolver;
use App\Services\Subscriptions\Communications\CommunicationConsentGate;
use App\Services\Subscriptions\Communications\SubscriptionCommunicationSender;
use App\Services\Subscriptions\Printing\PrintAddressResolver;
use Mockery;
use PHPUnit\Framework\TestCase;

class SubscriptionCommunicationSenderTest extends TestCase
{
    private SubscriptionCommunicationDeliveryRepository $deliveryRepository;
    private NotificationDispatcher                      $notificationDispatcher;
    private InAppNotificationDispatcher                 $inAppDispatcher;
    private Logger                                      $logger;
    private SubscriptionCommunicationLetterRepository    $letterRepository;
    private PrintAddressResolver                         $addressResolver;
    private Database                                     $database;
    private CommunicationChannelResolver                 $channelResolver;
    private CommunicationConsentGate                     $consentGate;
    private SubscriptionCommunicationSuppressionLogRepository $suppressionLog;
    private SubscriptionCommunicationSender             $sender;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deliveryRepository = Mockery::mock(SubscriptionCommunicationDeliveryRepository::class);
        $this->notificationDispatcher = Mockery::mock(NotificationDispatcher::class);
        $this->inAppDispatcher = Mockery::mock(InAppNotificationDispatcher::class);
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();
        $this->letterRepository = Mockery::mock(SubscriptionCommunicationLetterRepository::class);
        $this->addressResolver = Mockery::mock(PrintAddressResolver::class);
        $this->database = Mockery::mock(Database::class);
        $this->database->shouldReceive('transaction')
            ->andReturnUsing(function ($callback) {
                return $callback($this->database);
            });
        $this->channelResolver = Mockery::mock(CommunicationChannelResolver::class);
        $this->channelResolver->shouldReceive('resolve')
            ->andReturnUsing(function ($communication) {
                return $communication->channels ?? [];
            });
        $this->consentGate = Mockery::mock(CommunicationConsentGate::class);
        $this->consentGate->shouldReceive('evaluate')->andReturn(null)->byDefault();
        $this->consentGate->shouldReceive('evaluateChannel')->andReturn(null)->byDefault();
        $this->suppressionLog = Mockery::mock(SubscriptionCommunicationSuppressionLogRepository::class);
        $this->suppressionLog->shouldReceive('log')->byDefault();

        $this->sender = new SubscriptionCommunicationSender(
            $this->deliveryRepository,
            $this->notificationDispatcher,
            $this->inAppDispatcher,
            $this->logger,
            $this->letterRepository,
            $this->addressResolver,
            $this->database,
            $this->channelResolver,
            $this->consentGate,
            $this->suppressionLog,
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


    public function test_letter_send_creates_fulfilment_and_marks_sent(): void
    {
        $member = $this->makeMember(1, ''); // no email — letter path
        $sub    = $this->makeSubscription(1, $member);
        $comm   = $this->makeCommunication(channels: ['letter'], template: '');

        $delivery = $this->makeDelivery(id: 30, token: 'letter-token');
        $resolvedAddress = [
            'full_name' => 'Jane Doe',
            'address_line_1' => '1 Test Street',
            'address_line_2' => null,
            'city' => 'Christchurch',
            'postcode' => 'BH23 1AA',
            'country' => 'GB',
            'snapshot' => ['address_line_1' => '1 Test Street'],
        ];

        $this->deliveryRepository->shouldReceive('hasAlreadySent')->once()->andReturn(false);
        $this->addressResolver->shouldReceive('resolve')->once()->with($sub)->andReturn($resolvedAddress);

        $this->deliveryRepository
            ->shouldReceive('recordPending')
            ->once()
            ->andReturn($delivery);

        $this->letterRepository
            ->shouldReceive('createFulfilment')
            ->once()
            ->with(30, 1, 'PFN01', $resolvedAddress)
            ->andReturn(Mockery::mock(SubscriptionCommunicationLetterFulfilment::class)->makePartial());

        $this->deliveryRepository->shouldReceive('markSent')->once()->with(30);

        $this->sender->send(
            subscription: $sub,
            communication: $comm,
            metadata: ['letter_code' => 'PFN01'],
        );

        $this->assertTrue(true);
    }

    public function test_letter_send_skips_when_member_missing(): void
    {
        $sub = Mockery::mock(Subscription::class)->makePartial();
        $sub->id = 1;
        $sub->member = null;
        $comm = $this->makeCommunication(channels: ['letter'], template: '');

        $this->deliveryRepository->shouldReceive('hasAlreadySent')->once()->andReturn(false);
        $this->deliveryRepository->shouldReceive('recordPending')->never();
        $this->letterRepository->shouldReceive('createFulfilment')->never();

        $this->sender->send($sub, $comm);

        $this->assertTrue(true);
    }

    public function test_letter_send_does_not_persist_when_address_resolution_fails(): void
    {
        $member = $this->makeMember(1, '');
        $sub    = $this->makeSubscription(1, $member);
        $comm   = $this->makeCommunication(channels: ['letter'], template: '');

        $this->deliveryRepository->shouldReceive('hasAlreadySent')->once()->andReturn(false);
        $this->addressResolver->shouldReceive('resolve')->once()->andThrow(new \RuntimeException('No address'));

        $this->deliveryRepository->shouldReceive('recordPending')->never();
        $this->letterRepository->shouldReceive('createFulfilment')->never();

        $this->sender->send($sub, $comm);

        $this->assertTrue(true);
    }

    public function test_send_is_dropped_and_logged_when_consent_gate_blocks_whole_communication(): void
    {
        $member = $this->makeMember(1, 'member@example.com');
        $sub    = $this->makeSubscription(1, $member);
        $comm   = $this->makeCommunication(channels: ['email']);
        $comm->id = 5;

        $this->consentGate = Mockery::mock(CommunicationConsentGate::class);
        $this->consentGate->shouldReceive('evaluate')
            ->once()
            ->with($comm, $member)
            ->andReturn(\App\Enums\Subscriptions\CommunicationSuppressionReason::MEMBER_DECEASED);

        $this->suppressionLog->shouldReceive('log')
            ->once()
            ->with(
                subscriptionId: 1,
                memberId: 1,
                communicationId: 5,
                channel: null,
                reason: 'member_deceased',
            );

        $this->rebuildSender();
        $this->deliveryRepository->shouldReceive('recordPending')->never();
        $this->channelResolver->shouldReceive('resolve')->never();

        $this->sender->send($sub, $comm);

        $this->assertTrue(true);
    }

    public function test_send_is_dropped_at_channel_level_when_do_not_mail_blocks_letter(): void
    {
        $member = $this->makeMember(1, '');
        $sub    = $this->makeSubscription(1, $member);
        $comm   = $this->makeCommunication(channels: ['letter'], template: '');
        $comm->id = 6;

        $this->consentGate->shouldReceive('evaluateChannel')
            ->once()
            ->with($member, 'letter')
            ->andReturn(\App\Enums\Subscriptions\CommunicationSuppressionReason::DO_NOT_MAIL);

        $this->deliveryRepository->shouldReceive('hasAlreadySent')->once()->andReturn(false);
        $this->suppressionLog->shouldReceive('log')
            ->once()
            ->with(
                subscriptionId: 1,
                memberId: 1,
                communicationId: 6,
                channel: 'letter',
                reason: 'do_not_mail',
            );

        $this->deliveryRepository->shouldReceive('recordPending')->never();
        $this->letterRepository->shouldReceive('createFulfilment')->never();

        $this->sender->send($sub, $comm);

        $this->assertTrue(true);
    }

    private function rebuildSender(): void
    {
        $this->sender = new SubscriptionCommunicationSender(
            $this->deliveryRepository,
            $this->notificationDispatcher,
            $this->inAppDispatcher,
            $this->logger,
            $this->letterRepository,
            $this->addressResolver,
            $this->database,
            $this->channelResolver,
            $this->consentGate,
            $this->suppressionLog,
        );
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