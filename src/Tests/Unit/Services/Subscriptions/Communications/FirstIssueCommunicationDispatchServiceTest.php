<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions\Communications;

use App\Models\SubscriptionCommunication;
use App\Models\SubscriptionCommunicationLetterCode;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionCommunicationLetterCodeRepository;
use App\Repositories\Subscriptions\SubscriptionCommunicationRepository;
use App\Services\Subscriptions\Communications\FirstIssueCommunicationDispatchService;
use App\Services\Subscriptions\Communications\SubscriptionCommunicationSender;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Scope (site/product enable-disable) is deliberately NOT tested here —
 * it's no longer this service's concern. SubscriptionCommunicationSender
 * checks scope universally for every communication; see
 * SubscriptionCommunicationSenderTest::test_send_is_dropped_and_logged_when_scope_disabled.
 */
class FirstIssueCommunicationDispatchServiceTest extends TestCase
{
    private SubscriptionCommunicationRepository $communications;
    private SubscriptionCommunicationLetterCodeRepository $letterCodes;
    private SubscriptionCommunicationSender $sender;
    private FirstIssueCommunicationDispatchService $dispatchService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->communications = Mockery::mock(SubscriptionCommunicationRepository::class);
        $this->letterCodes = Mockery::mock(SubscriptionCommunicationLetterCodeRepository::class);
        $this->sender = Mockery::mock(SubscriptionCommunicationSender::class);

        $this->dispatchService = new FirstIssueCommunicationDispatchService(
            $this->communications,
            $this->letterCodes,
            $this->sender,
        );
    }

    public function test_throws_when_communication_not_configured(): void
    {
        $this->communications->shouldReceive('findActiveByKey')
            ->once()
            ->with('first_issue_default')
            ->andReturn(null);

        $this->expectException(\RuntimeException::class);

        $this->dispatchService->dispatch($this->makeSubscription());
    }

    public function test_sends_with_letter_code_when_registered(): void
    {
        $subscription = $this->makeSubscription();
        $communication = $this->makeCommunication();
        $letterCode = Mockery::mock(SubscriptionCommunicationLetterCode::class)->makePartial();
        $letterCode->letter_code = 'FIN01';

        $this->communications->shouldReceive('findActiveByKey')->once()->andReturn($communication);
        $this->letterCodes->shouldReceive('findForCommunication')->once()->with(1)->andReturn($letterCode);

        $this->sender->shouldReceive('send')
            ->once()
            ->with(
                subscription: $subscription,
                communication: $communication,
                metadata: ['letter_code' => 'FIN01'],
                dedupeKey: 'first-issue:subscription:100',
            );

        $this->dispatchService->dispatch($subscription);

        $this->assertTrue(true);
    }

    public function test_sends_without_letter_code_metadata_when_none_registered(): void
    {
        $subscription = $this->makeSubscription();
        $communication = $this->makeCommunication();

        $this->communications->shouldReceive('findActiveByKey')->once()->andReturn($communication);
        $this->letterCodes->shouldReceive('findForCommunication')->once()->andReturn(null);

        $this->sender->shouldReceive('send')
            ->once()
            ->with(
                subscription: $subscription,
                communication: $communication,
                metadata: [],
                dedupeKey: 'first-issue:subscription:100',
            );

        $this->dispatchService->dispatch($subscription);

        $this->assertTrue(true);
    }

    private function makeSubscription(): Subscription
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 100;
        $subscription->site_id = 10;
        $subscription->plan_id = 20;
        return $subscription;
    }

    private function makeCommunication(): SubscriptionCommunication
    {
        $communication = Mockery::mock(SubscriptionCommunication::class)->makePartial();
        $communication->id = 1;
        return $communication;
    }
}
