<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions\Communications;

use App\Models\SubscriptionCommunication;
use App\Models\SubscriptionCommunicationLetterCode;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionCommunicationLetterCodeRepository;
use App\Repositories\Subscriptions\SubscriptionCommunicationRepository;
use App\Repositories\Subscriptions\SubscriptionCommunicationScopeRepository;
use App\Services\Subscriptions\Communications\FirstIssueCommunicationDispatchService;
use App\Services\Subscriptions\Communications\SubscriptionCommunicationSender;
use Mockery;
use PHPUnit\Framework\TestCase;

class FirstIssueCommunicationDispatchServiceTest extends TestCase
{
    private SubscriptionCommunicationRepository $communications;
    private SubscriptionCommunicationScopeRepository $scopes;
    private SubscriptionCommunicationLetterCodeRepository $letterCodes;
    private SubscriptionCommunicationSender $sender;
    private FirstIssueCommunicationDispatchService $dispatchService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->communications = Mockery::mock(SubscriptionCommunicationRepository::class);
        $this->scopes = Mockery::mock(SubscriptionCommunicationScopeRepository::class);
        $this->letterCodes = Mockery::mock(SubscriptionCommunicationLetterCodeRepository::class);
        $this->sender = Mockery::mock(SubscriptionCommunicationSender::class);

        $this->dispatchService = new FirstIssueCommunicationDispatchService(
            $this->communications,
            $this->scopes,
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

    public function test_does_not_send_when_disabled_for_scope(): void
    {
        $subscription = $this->makeSubscription();
        $communication = $this->makeCommunication();

        $this->communications->shouldReceive('findActiveByKey')->once()->andReturn($communication);
        $this->scopes->shouldReceive('isEnabled')
            ->once()
            ->with(1, 10, 20)
            ->andReturn(false);

        $this->sender->shouldReceive('send')->never();

        $this->dispatchService->dispatch($subscription);

        $this->assertTrue(true);
    }

    public function test_sends_with_letter_code_when_enabled_for_scope(): void
    {
        $subscription = $this->makeSubscription();
        $communication = $this->makeCommunication();
        $letterCode = Mockery::mock(SubscriptionCommunicationLetterCode::class)->makePartial();
        $letterCode->letter_code = 'FIN01';

        $this->communications->shouldReceive('findActiveByKey')->once()->andReturn($communication);
        $this->scopes->shouldReceive('isEnabled')->once()->with(1, 10, 20)->andReturn(true);
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
        $this->scopes->shouldReceive('isEnabled')->once()->andReturn(true);
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
