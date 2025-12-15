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

    public function testSubscribeReactivatesCancelledStripeSubscription(): void
    {
        $this->actingAsMember($this->member);

        // Create cancelled subscription with Stripe ID
        $cancelledSubscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_id' => $this->plan->id,
            'plan_name' => $this->plan->name,
            'status' => 'cancelled',
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 month')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+5 days')),
            'price' => 29.99,
            'currency' => 'USD',
            'payment_subscription_id' => 'sub_test123'
        ]);

        $response = $this->postForSite(
            '/member/subscription-plans/premium/subscribe',
            ['payment_method' => 'credit_card'],
            [],
            ['X-Requested-With' => 'XMLHttpRequest']
        );

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertStringContainsString('reactivated', strtolower($data['message']));

        // Verify subscription is now active
        $updated = Subscription::find($cancelledSubscription->id);
        $this->assertEquals('active', $updated->status);
        $this->assertTrue($updated->auto_renew);
    }

    public function testSubscribeWithValidVoucher(): void
    {
        $this->actingAsMember($this->member);

        $voucher = \App\Models\Voucher::create([
            'code' => 'SUB10',
            'name' => '10% Off',
            'type' => 'percentage',
            'value' => 10,
            'status' => 'active',
            'site_id' => $this->siteId,
            'applies_to_subscriptions' => true
        ]);

        $response = $this->postForSite(
            '/member/subscription-plans/premium/subscribe',
            [
                'payment_method' => 'stripe',
                'voucher_code' => 'SUB10'
            ],
            [],
            ['X-Requested-With' => 'XMLHttpRequest']
        );

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertTrue($data['data']['discount_applied']);
        $this->assertGreaterThan(0, $data['data']['discount_amount']);
        $this->assertLessThan($this->plan->price, $data['data']['final_price']);
    }

    public function testSubscribeWithInvalidVoucher(): void
    {
        $this->actingAsMember($this->member);

        $response = $this->postForSite(
            '/member/subscription-plans/premium/subscribe',
            [
                'payment_method' => 'stripe',
                'voucher_code' => 'INVALID'
            ],
            [],
            ['X-Requested-With' => 'XMLHttpRequest']
        );

        $this->assertEquals(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Voucher', $data['message']);
    }

    public function testSubscribeWithoutVoucher(): void
    {
        $this->actingAsMember($this->member);

        $response = $this->postForSite(
            '/member/subscription-plans/premium/subscribe',
            ['payment_method' => 'stripe'],
            [],
            ['X-Requested-With' => 'XMLHttpRequest']
        );

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
    }

    public function testValidateVoucherSuccess(): void
    {
        $this->actingAsMember($this->member);

        $voucher = \App\Models\Voucher::create([
            'code' => 'SUB20',
            'name' => '20% Off',
            'type' => 'percentage',
            'value' => 20,
            'status' => 'active',
            'site_id' => $this->siteId,
            'applies_to_subscriptions' => true
        ]);

        $response = $this->postForSite(
            '/member/subscription-plans/premium/validate-voucher',
            ['voucher_code' => 'SUB20']
        );

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('discount', $data['data']);
        $this->assertArrayHasKey('original_price', $data['data']);
        $this->assertArrayHasKey('final_price', $data['data']);
        $this->assertEquals(round(20 * $this->plan->price / 100, 2), $data['data']['discount']);
    }

    public function testValidateVoucherNotFound(): void
    {
        $this->actingAsMember($this->member);

        $response = $this->postForSite(
            '/member/subscription-plans/premium/validate-voucher',
            ['voucher_code' => 'NOTFOUND']
        );

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['success']);
        $this->assertEquals('Voucher not found', $data['message']);
    }

    public function testValidateVoucherRequiresAuthentication(): void
    {
        $response = $this->postForSite(
            '/member/subscription-plans/premium/validate-voucher',
            ['voucher_code' => 'TEST']
        );

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testValidateVoucherRequiresCode(): void
    {
        $this->actingAsMember($this->member);

        $response = $this->postForSite(
            '/member/subscription-plans/premium/validate-voucher',
            []
        );

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['success']);
        $this->assertEquals('Voucher code is required', $data['message']);
    }

    public function testValidateVoucherNotApplicableToSubscriptions(): void
    {
        $this->actingAsMember($this->member);

        $voucher = \App\Models\Voucher::create([
            'code' => 'PRODUCT10',
            'name' => 'Product Discount',
            'type' => 'percentage',
            'value' => 10,
            'status' => 'active',
            'site_id' => $this->siteId,
            'applies_to_subscriptions' => false
        ]);

        $response = $this->postForSite(
            '/member/subscription-plans/premium/validate-voucher',
            ['voucher_code' => 'PRODUCT10']
        );

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['success']);
        $this->assertStringContainsString('cannot be used for subscriptions', $data['message']);
    }

}