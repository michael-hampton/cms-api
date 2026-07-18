<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions\Communications;

use App\DTO\Subscriptions\PaymentCommunicationEligibilityResult;
use App\Enums\Subscriptions\PaymentCommunicationEventType;
use App\Models\Member;
use App\Models\Subscription;
use App\Models\SubscriptionCommunication;
use App\Models\SubscriptionCommunicationLetterCode;
use App\Repositories\Subscriptions\SubscriptionCommunicationLetterCodeRepository;
use App\Repositories\Subscriptions\SubscriptionCommunicationRepository;
use App\Services\Subscriptions\Communications\PaymentCommunicationDispatchService;
use App\Services\Subscriptions\Communications\PaymentCommunicationEligibilityResolver;
use App\Services\Subscriptions\Communications\SubscriptionCommunicationSender;
use Mockery;
use PHPUnit\Framework\TestCase;

class PaymentCommunicationDispatchServiceTest extends TestCase
{
    private SubscriptionCommunicationRepository $communications;
    private SubscriptionCommunicationLetterCodeRepository $letterCodes;
    private PaymentCommunicationEligibilityResolver $eligibility;
    private SubscriptionCommunicationSender $sender;
    private PaymentCommunicationDispatchService $dispatchService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->communications = Mockery::mock(SubscriptionCommunicationRepository::class);
        $this->letterCodes = Mockery::mock(SubscriptionCommunicationLetterCodeRepository::class);
        $this->eligibility = Mockery::mock(PaymentCommunicationEligibilityResolver::class);
        $this->sender = Mockery::mock(SubscriptionCommunicationSender::class);

        $this->dispatchService = new PaymentCommunicationDispatchService(
            $this->communications,
            $this->letterCodes,
            $this->eligibility,
            $this->sender,
        );
    }

    public function test_throws_when_communication_not_configured(): void
    {
        $subscription = $this->makeSubscription();

        $this->communications->shouldReceive('findActiveByKey')
            ->once()
            ->with('payment_failed_letter_default')
            ->andReturn(null);

        $this->expectException(\RuntimeException::class);

        $this->dispatchService->dispatch(PaymentCommunicationEventType::PAYMENT_FAILED, $subscription);
    }

    public function test_does_not_send_when_not_eligible(): void
    {
        $subscription = $this->makeSubscription();
        $communication = $this->makeCommunication();

        $this->communications->shouldReceive('findActiveByKey')->once()->andReturn($communication);
        $this->eligibility->shouldReceive('resolve')
            ->once()
            ->andReturn(PaymentCommunicationEligibilityResult::skipped('member_has_email'));

        $this->letterCodes->shouldReceive('findForCommunication')->never();
        $this->sender->shouldReceive('send')->never();

        $this->dispatchService->dispatch(PaymentCommunicationEventType::PAYMENT_FAILED, $subscription);

        $this->assertTrue(true);
    }

    public function test_throws_when_no_letter_code_registered(): void
    {
        $subscription = $this->makeSubscription();
        $communication = $this->makeCommunication();

        $this->communications->shouldReceive('findActiveByKey')->once()->andReturn($communication);
        $this->eligibility->shouldReceive('resolve')->once()
            ->andReturn(PaymentCommunicationEligibilityResult::eligible());
        $this->letterCodes->shouldReceive('findForCommunication')->once()->with(1)->andReturn(null);

        $this->sender->shouldReceive('send')->never();

        $this->expectException(\RuntimeException::class);

        $this->dispatchService->dispatch(PaymentCommunicationEventType::PAYMENT_FAILED, $subscription);
    }

    public function test_sends_with_letter_code_and_dedupe_key_when_eligible(): void
    {
        $subscription = $this->makeSubscription();
        $communication = $this->makeCommunication();
        $letterCode = Mockery::mock(SubscriptionCommunicationLetterCode::class)->makePartial();
        $letterCode->letter_code = 'PFN01';

        $this->communications->shouldReceive('findActiveByKey')
            ->once()
            ->with('payment_failed_letter_default')
            ->andReturn($communication);

        $this->eligibility->shouldReceive('resolve')->once()
            ->andReturn(PaymentCommunicationEligibilityResult::eligible());

        $this->letterCodes->shouldReceive('findForCommunication')->once()->with(1)->andReturn($letterCode);

        $this->sender->shouldReceive('send')
            ->once()
            ->with(
                subscription: $subscription,
                communication: $communication,
                metadata: Mockery::on(fn (array $m) => $m['letter_code'] === 'PFN01' && $m['failure_reason'] === 'card_declined'),
                dedupeKey: 'invoice.payment_failed:subscription:100',
            );

        $this->dispatchService->dispatch(
            PaymentCommunicationEventType::PAYMENT_FAILED,
            $subscription,
            ['failure_reason' => 'card_declined'],
        );

        $this->assertTrue(true);
    }

    private function makeSubscription(): Subscription
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $member->email = '';

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 100;
        $subscription->site_id = 10;
        $subscription->plan_id = 20;
        $subscription->member = $member;

        return $subscription;
    }

    private function makeCommunication(): SubscriptionCommunication
    {
        $communication = Mockery::mock(SubscriptionCommunication::class)->makePartial();
        $communication->id = 1;
        return $communication;
    }
}
