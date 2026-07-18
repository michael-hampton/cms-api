<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions\Communications;

use App\Models\Member;
use App\Models\Subscription;
use App\Models\SubscriptionCommunication;
use App\Services\Subscriptions\Communications\PaymentCommunicationEligibilityResolver;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Scope (site/product enable-disable) is deliberately NOT tested here —
 * it's no longer this resolver's concern. SubscriptionCommunicationSender
 * checks scope universally for every communication; see
 * SubscriptionCommunicationSenderTest::test_send_is_dropped_and_logged_when_scope_disabled.
 * This resolver only owns the "letter requires no email on file" rule.
 */
class PaymentCommunicationEligibilityResolverTest extends TestCase
{
    private PaymentCommunicationEligibilityResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new PaymentCommunicationEligibilityResolver();
    }

    public function test_skips_when_member_is_null(): void
    {
        $communication = $this->makeCommunication();
        $subscription = $this->makeSubscription();

        $result = $this->resolver->resolve($communication, $subscription, null);

        $this->assertFalse($result->eligible);
        $this->assertSame('no_member', $result->reason);
    }

    public function test_skips_when_member_has_email(): void
    {
        $communication = $this->makeCommunication();
        $subscription = $this->makeSubscription();
        $member = $this->makeMember('member@example.com');

        $result = $this->resolver->resolve($communication, $subscription, $member);

        $this->assertFalse($result->eligible);
        $this->assertSame('member_has_email', $result->reason);
    }

    public function test_eligible_when_member_has_no_email(): void
    {
        $communication = $this->makeCommunication();
        $subscription = $this->makeSubscription();
        $member = $this->makeMember('');

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
