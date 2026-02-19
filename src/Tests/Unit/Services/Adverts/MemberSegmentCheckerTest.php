<?php

namespace App\Tests\Unit\Services\Adverts;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\Adverts\MemberSegmentChecker;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MemberSegmentCheckerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private MemberSegmentChecker $checker;

    public function testIsInSegmentReturnsTrueWhenSegmentMatches(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Premium Plan',
            'slug' => 'premium',
            'price' => 99.99,
            'currency' => 'USD',
            'billing_period' => 'yearly',
            'plan_type' => 'recurring',
            'is_active' => true,
        ]);

        $member = $this->createMember();

        Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_id' => $plan->id,
            'status' => 'active',
            'type' => 'paid',
            'start_date' => date('Y-m-d H:i:s'),
            'plan_name' => 'Premium Plan'
        ]);

        $this->assertTrue($this->checker->isInSegment($member, 'premium'));
    }

    public function testIsInSegmentReturnsFalseWhenSegmentDoesNotMatch(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Basic Plan',
            'slug' => 'basic',
            'price' => 9.99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'plan_type' => 'recurring',
            'is_active' => true,
        ]);

        $member = $this->createMember();

        Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_id' => $plan->id,
            'status' => 'active',
            'type' => 'paid',
            'start_date' => date('Y-m-d H:i:s'),
            'plan_name' => 'Premium Plan'
        ]);

        $this->assertFalse($this->checker->isInSegment($member, 'premium'));
    }

    public function testIsInAnySegmentReturnsTrueWhenAtLeastOneMatches(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Premium Plan',
            'slug' => 'premium',
            'price' => 99.99,
            'currency' => 'USD',
            'billing_period' => 'yearly',
            'plan_type' => 'recurring',
            'is_active' => true,
        ]);

        $member = $this->createMember();

        Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_id' => $plan->id,
            'status' => 'active',
            'type' => 'paid',
            'start_date' => date('Y-m-d H:i:s'),
            'plan_name' => 'Premium Plan'
        ]);

        $this->assertTrue($this->checker->isInAnySegment($member, ['basic', 'premium', 'onetime']));
    }

    public function testIsInAnySegmentReturnsFalseWhenNoneMatch(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Basic Plan',
            'slug' => 'basic',
            'price' => 9.99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'plan_type' => 'recurring',
            'is_active' => true,
        ]);

        $member = $this->createMember();

        Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_id' => $plan->id,
            'status' => 'active',
            'type' => 'paid',
            'start_date' => date('Y-m-d H:i:s'),
            'plan_name' => 'Premium Plan'
        ]);

        $this->assertFalse($this->checker->isInAnySegment($member, ['premium', 'onetime']));
    }

    public function testGetCurrentSegmentReturnsCorrectSegment(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Premium Plan',
            'slug' => 'premium',
            'price' => 99.99,
            'currency' => 'USD',
            'billing_period' => 'yearly',
            'plan_type' => 'recurring',
            'is_active' => true,
        ]);

        $member = $this->createMember();

        Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_id' => $plan->id,
            'status' => 'active',
            'type' => 'paid',
            'start_date' => date('Y-m-d H:i:s'),
            'plan_name' => 'Premium Plan'
        ]);

        $this->assertEquals('premium', $this->checker->getCurrentSegment($member));
    }

    public function testGetCurrentSegmentReturnsFreeForNoSubscription(): void
    {
        $member = $this->createMember();
        $this->assertEquals('free', $this->checker->getCurrentSegment($member));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new MemberSegmentChecker();
    }
}