<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions\Communications;

use App\Models\Member;
use App\Models\Subscription;
use App\Models\SubscriptionCommunication;
use App\Repositories\Subscriptions\SubscriptionCommunicationScopeRepository;
use App\Services\Subscriptions\Communications\PaymentCommunicationEligibilityResolver;
use Mockery;
use PHPUnit\Framework\TestCase;

class PaymentCommunicationEligibilityResolverTest extends TestCase
{
    private SubscriptionCommunicationScopeRepository $scopes;
    private PaymentCommunicationEligibilityResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scopes = Mockery::mock(SubscriptionCommunicationScopeRepository::class);
        $this->resolver = new PaymentCommunicationEligibilityResolver($this->scopes);
    }

    public function test_skips_when_member_is_null(): void
    {
        $communication = $this->makeCommunication();
        $subscription = $this->makeSubscription();

        $this->scopes->shouldReceive('isEnabled')->never();

        $result = $this->resolver->resolve($communication, $subscription, null);

        $this->assertFalse($result->eligible);
        $this->assertSame('no_member', $result->reason);
    }

    public function test_skips_when_member_has_email(): void
    {
        $communication = $this->makeCommunication();
        $subscription = $this->makeSubscription();
        $member = $this->makeMember('member@example.com');

        $this->scopes->shouldReceive('isEnabled')->never();

        $result = $this->resolver->resolve($communication, $subscription, $member);

        $this->assertFalse($result->eligible);
        $this->assertSame('member_has_email', $result->reason);
    }

    public function test_skips_when_scope_disabled(): void
    {
        $communication = $this->makeCommunication();
        $subscription = $this->makeSubscription();
        $member = $this->makeMember(null);

        $this->scopes->shouldReceive('isEnabled')
            ->once()
            ->with(1, 10, 20)
            ->andReturn(false);

        $result = $this->resolver->resolve($communication, $subscription, $member);

        $this->assertFalse($result->eligible);
        $this->assertSame('disabled_for_scope', $result->reason);
    }

    public function test_eligible_when_no_email_and_scope_enabled(): void
    {
        $communication = $this->makeCommunication();
        $subscription = $this->makeSubscription();
        $member = $this->makeMember('');

        $this->scopes->shouldReceive('isEnabled')
            ->once()
            ->with(1, 10, 20)
            ->andReturn(true);

        $result = $this->resolver->resolve($communication, $subscription, $member);

        $this->assertTrue($result->eligible);
        $this->assertNull($result->reason);
    }

    private function makeCommunication(): SubscriptionCommunication
    {
        $communication = Mockery::mock(SubscriptionCommunication::class)->makePartial();
        $communication->id = 1;
        return $communication;
    }

    private function makeSubscription(): Subscription
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 100;
        $subscription->site_id = 10;
        $subscription->plan_id = 20;
        return $subscription;
    }

    private function makeMember(?string $email): Member
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $member->email = $email;
        return $member;
    }
}
