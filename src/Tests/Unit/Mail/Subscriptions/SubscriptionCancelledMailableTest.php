<?php

namespace App\Tests\Unit\Mail\Subscriptions;

use App\Framework\Mail\ArrayMailer;
use App\Mail\Subscriptions\SubscriptionCancelledMailable;
use App\Models\Member;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class SubscriptionCancelledMailableTest extends FunctionalTestCase
{
    public function test_it_builds_with_correct_subject(): void
    {
        $mailable = new SubscriptionCancelledMailable(
            $this->makeSubscription(),
            new \DateTimeImmutable('2025-07-01'),
        );
        $mailable->build();

        $this->assertStringContainsString('cancelled', $mailable->subject);
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
        $mailable = new SubscriptionCancelledMailable(
            $this->makeSubscription(),
            new \DateTimeImmutable('2025-07-01'),
        );
        $mailable->build();

        $this->assertSame('member@example.com', $mailable->to[0]['address']);
    }

    public function test_it_renders_the_access_until_date(): void
    {
        $mailable = new SubscriptionCancelledMailable(
            $this->makeSubscription(),
            new \DateTimeImmutable('2025-07-01'),
        );
        $mailable->build();
        $body = $mailable->render();

        $this->assertStringContainsString('1 July 2025', $body);
    }

    public function test_it_renders_the_plan_name(): void
    {
        $mailable = new SubscriptionCancelledMailable(
            $this->makeSubscription(),
            new \DateTimeImmutable('2025-07-01'),
        );
        $mailable->build();
        $body = $mailable->render();

        $this->assertStringContainsString('Premium Monthly', $body);
    }

    public function test_it_communicates_that_access_continues_until_period_end(): void
    {
        $mailable = new SubscriptionCancelledMailable(
            $this->makeSubscription(),
            new \DateTimeImmutable('2025-07-01'),
        );
        $mailable->build();
        $body = $mailable->render();

        $this->assertStringContainsString('continue to have full access', $body);
    }

    public function test_it_confirms_no_further_payments(): void
    {
        $mailable = new SubscriptionCancelledMailable(
            $this->makeSubscription(),
            new \DateTimeImmutable('2025-07-01'),
        );
        $mailable->build();
        $body = $mailable->render();

        $this->assertStringContainsString('No further payments', $body);
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