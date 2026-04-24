<?php

namespace App\Tests\Unit\Mail\Subscriptions;

use App\Framework\Mail\ArrayMailer;
use App\Mail\Subscriptions\PaymentReceivedMailable;
use App\Models\Member;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class PaymentReceivedMailableTest extends FunctionalTestCase
{
    public function test_it_builds_with_correct_subject(): void
    {
        $mailable = new PaymentReceivedMailable($this->makeSubscription());
        $mailable->build();

        $this->assertStringContainsString('Payment received', $mailable->subject);
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
        $mailable = new PaymentReceivedMailable($this->makeSubscription());
        $mailable->build();

        $this->assertNotEmpty($mailable->to);
        $this->assertSame('member@example.com', $mailable->to[0]['address']);
    }

    public function test_it_renders_the_plan_name_in_body(): void
    {
        $mailable = new PaymentReceivedMailable($this->makeSubscription());
        $mailable->build();
        $body = $mailable->render();

        $this->assertStringContainsString('Premium Monthly', $body);
    }

    public function test_it_renders_the_access_until_date_in_body(): void
    {
        $subscription = $this->makeSubscription();
        $subscription->current_period_end = new \DateTime('2025-07-01');

        $mailable = new PaymentReceivedMailable($subscription);
        $mailable->build();
        $body = $mailable->render();

        $this->assertStringContainsString('1 July 2025', $body);
    }

    public function test_it_renders_the_payment_amount(): void
    {
        $mailable = new PaymentReceivedMailable($this->makeSubscription());
        $mailable->build();
        $body = $mailable->render();

        $this->assertStringContainsString('29.00', $body);
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