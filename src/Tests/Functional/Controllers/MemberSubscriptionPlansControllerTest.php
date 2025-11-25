<?php

namespace App\Tests\Functional\Controllers;

use App\Models\Member;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MemberSubscriptionPlansControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private Member $member;
    private SubscriptionPlan $plan;

    public function testIndexDisplaysAvailablePlans(): void
    {
        $response = $this->getForSite('/member/subscription-plans');

        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertStringContainsString('Choose Your Plan', $content);
        $this->assertStringContainsString('Premium Plan', $content);
    }

    public function testShowDisplaysPlanDetails(): void
    {
        $response = $this->getForSite('/member/subscription-plans/premium');

        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertStringContainsString('Premium Plan', $content);
        $this->assertStringContainsString('29.99', $content);
        $this->assertStringContainsString('7 Day Free Trial', $content);
    }

    public function testShowReturns404ForInvalidSlug(): void
    {
        $response = $this->getForSite('/member/subscription-plans/invalid');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testSubscribeRequiresAuthentication(): void
    {
        $response = $this->postForSite('/member/subscription-plans/premium/subscribe');

        $this->assertEquals(302, $response->getStatusCode());
    }

    public function testSubscribeCreatesSubscription(): void
    {
        $this->actingAsMember($this->member);

        $response = $this->postForSite(
            '/member/subscription-plans/premium/subscribe',
            ['payment_method' => 'credit_card']
        );

        $this->assertEquals(302, $response->getStatusCode());

        $subscription = Subscription::where('member_id', $this->member->id)
            ->where('plan_id', $this->plan->id)
            ->first();

        $this->assertNotNull($subscription);
        $this->assertEquals('active', $subscription->status);
        $this->assertEquals(29.99, $subscription->price);
    }

    public function testSubscribeReturnsJsonForAjax(): void
    {
        $this->actingAsMember($this->member);

        $response = $this->postForSite(
            '/member/subscription-plans/premium/subscribe',
            [],
            [],
            ['X-Requested-With' => 'XMLHttpRequest']
        );

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('subscription', $data['data']);
    }

    public function testSubscribeFailsIfAlreadySubscribed(): void
    {
        $this->actingAsMember($this->member);

        // Create existing subscription
        Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_id' => $this->plan->id,
            'plan_name' => $this->plan->name,
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        $response = $this->postForSite(
            '/member/subscription-plans/premium/subscribe',
            [],
            [],
            ['X-Requested-With' => 'XMLHttpRequest']
        );

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['success']);
        $this->assertStringContainsString('already', strtolower($data['message']));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = $this->createMember();

        $this->plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Premium Plan',
            'slug' => 'premium',
            'description' => 'Premium features',
            'price' => 29.99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'trial_days' => 7,
            'features' => ['Feature 1', 'Feature 2'],
            'is_active' => true,
            'is_featured' => true
        ]);
    }
}