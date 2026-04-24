<?php

namespace App\Tests\Unit\Mail\Subscriptions;

use App\Framework\Mail\ArrayMailer;
use App\Mail\Subscriptions\PaymentFailedMailable;
use App\Models\Member;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class PaymentFailedMailableTest extends FunctionalTestCase
{
    public function test_it_builds_with_correct_subject(): void
    {
        $mailable = new PaymentFailedMailable(
            $this->makeSubscription(),
            new \DateTimeImmutable('2025-06-08'),
            null,
        );
        $mailable->build();

        $this->assertStringContainsString('payment issue', $mailable->subject);
        $this->assertStringContainsString('Premium Monthly', $mailable->subject);
    }

    private function makeSubscription(): Subscription
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $member->email = 'member@example.com';
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

    public function test_it_addresses_the_member(): void
    {
        $mailable = new PaymentFailedMailable(
            $this->makeSubscription(),
            new \DateTimeImmutable('2025-06-08'),
            null,
        );
        $mailable->build();

        $this->assertSame('member@example.com', $mailable->to[0]['address']);
    }

    public function test_it_renders_the_grace_period_date(): void
    {
        $mailable = new PaymentFailedMailable(
            $this->makeSubscription(),
            new \DateTimeImmutable('2025-06-08'),
            null,
        );
        $mailable->build();
        $body = $mailable->render();

        $this->assertStringContainsString('8 June 2025', $body);
    }

    public function test_it_renders_the_failure_reason_when_provided(): void
    {
        $mailable = new PaymentFailedMailable(
            $this->makeSubscription(),
            new \DateTimeImmutable('2025-06-08'),
            'Your card was declined.',
        );
        $mailable->build();
        $body = $mailable->render();

        $this->assertStringContainsString('Your card was declined.', $body);
    }

    public function test_it_omits_reason_line_when_no_failure_reason(): void
    {
        $mailable = new PaymentFailedMailable(
            $this->makeSubscription(),
            new \DateTimeImmutable('2025-06-08'),
            null,
        );
        $mailable->build();
        $body = $mailable->render();

        $this->assertStringNotContainsString('The reason given was', $body);
    }

    public function test_it_renders_reassuring_access_retained_message(): void
    {
        $mailable = new PaymentFailedMailable(
            $this->makeSubscription(),
            new \DateTimeImmutable('2025-06-08'),
            null,
        );
        $mailable->build();
        $body = $mailable->render();

        $this->assertStringContainsString('access is not affected yet', $body);
    }

    protected function setUp(): void
    {
        parent::setUp();
        ArrayMailer::clear();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}