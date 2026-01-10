<?php

namespace App\Tests\Functional\Controllers\Members\Subscriptions;

use App\Models\Member;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MemberSubscriptionUpgradeControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private Member $member;
    private Subscription $subscription;
    private SubscriptionPlan $currentPlan;
    private SubscriptionPlan $upgradePlan;

//    public function testIndexDisplaysUpgradeOptions(): void
//    {
//        $response = $this->getForSite("/member/subscriptions/{$this->subscription->id}/upgrade");
//
//        $this->assertEquals(200, $response->getStatusCode());
//        $content = $response->getContent();
//        $this->assertStringContainsString('Upgrade', $content);
//        $this->assertStringContainsString($this->upgradePlan->name, $content);
//    }

    public function testIndexRequiresAuthentication(): void
    {
        $this->unauthenticateMember();

        $response = $this->getForSite("/member/subscriptions/{$this->subscription->id}/upgrade");

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/member/login', $response->getHeader('Location'));
    }

    public function testIndexReturns404ForNonExistentSubscription(): void
    {
        $response = $this->getForSite('/member/subscriptions/99999/upgrade');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testPreviewReturnsUpgradeCalculation(): void
    {
        $response = $this->postForSite(
            "/member/subscriptions/{$this->subscription->id}/upgrade/preview",
            ['upgrade_plan_id' => $this->upgradePlan->id]
        );

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('prorated', $data['data']['pricing']);
        $this->assertArrayHasKey('upgrade_price', $data['data']['pricing']);
    }

    public function testPreviewRequiresAuthentication(): void
    {
        $this->unauthenticateMember();

        $response = $this->postForSite(
            "/member/subscriptions/{$this->subscription->id}/upgrade/preview",
            ['upgrade_plan_id' => $this->upgradePlan->id]
        );

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testPreviewReturnsErrorForInvalidPlan(): void
    {
        $response = $this->postForSite(
            "/member/subscriptions/{$this->subscription->id}/upgrade/preview",
            ['upgrade_plan_id' => 99999]
        );

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function testUpgradeProcessesSuccessfully(): void
    {
        $response = $this->postForSite(
            "/member/subscriptions/{$this->subscription->id}/upgrade",
            [
                'upgrade_plan_id' => $this->upgradePlan->id,
                'payment_method_id' => 'pm_test123'
            ]
        );

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('subscription', $data['data']);
        $this->assertArrayHasKey('price_charged', $data['data']);

        // Verify subscription was upgraded
        $updated = Subscription::find($this->subscription->id);
        $this->assertEquals($this->upgradePlan->id, $updated->plan_id);
        $this->assertEquals($this->upgradePlan->price, $updated->price);
    }

    public function testUpgradeRequiresAuthentication(): void
    {
        $this->unauthenticateMember();

        $response = $this->postForSite(
            "/member/subscriptions/{$this->subscription->id}/upgrade",
            ['upgrade_plan_id' => $this->upgradePlan->id]
        );

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testUpgradeReturnsErrorForInvalidPlan(): void
    {
        $response = $this->postForSite(
            "/member/subscriptions/{$this->subscription->id}/upgrade",
            ['upgrade_plan_id' => 99999]
        );

        $this->assertEquals(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

//    public function testUpgradeReturnsErrorWithoutPaymentMethod(): void
//    {
//        $response = $this->postForSite(
//            "/member/subscriptions/{$this->subscription->id}/upgrade",
//            ['upgrade_plan_id' => $this->upgradePlan->id]
//        );
//
//        echo '<pre>';
//        print_r($response->getContent());
//        die;
//
//        $this->assertEquals(500, $response->getStatusCode());
//        $data = json_decode($response->getContent(), true);
//        $this->assertFalse($data['success']);
//    }

    public function testUpgradeCannotUpgradeOtherMembersSubscription(): void
    {
        $otherMember = $this->createMember(['email' => 'other@example.com']);
        $otherSubscription = Subscription::create([
            'member_id' => $otherMember->id,
            'site_id' => $this->siteId,
            'plan_id' => $this->currentPlan->id,
            'plan_name' => $this->currentPlan->name,
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => $this->currentPlan->price,
            'currency' => 'USD'
        ]);

        $response = $this->postForSite(
            "/member/subscriptions/{$otherSubscription->id}/upgrade",
            [
                'upgrade_plan_id' => $this->upgradePlan->id,
                'payment_method_id' => 'pm_test123'
            ]
        );

        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testUpgradeIncludesClientSecretForPaymentConfirmation(): void
    {
        $response = $this->postForSite(
            "/member/subscriptions/{$this->subscription->id}/upgrade",
            [
                'upgrade_plan_id' => $this->upgradePlan->id,
                'payment_method_id' => 'pm_test123'
            ]
        );

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        // Client secret may be null if payment succeeds immediately
        $this->assertArrayHasKey('client_secret', $data['data']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = $this->createMember();
        $this->actingAsMember($this->member);

        $this->currentPlan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Basic Plan',
            'slug' => 'basic',
            'description' => 'Basic features',
            'price' => 19.99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true
        ]);

        $this->upgradePlan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Premium Plan',
            'slug' => 'premium',
            'description' => 'Premium features',
            'price' => 39.99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'is_upgrade_option' => true,
            'upgrade_from_plan_id' => $this->currentPlan->id
        ]);

        $this->subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_id' => $this->currentPlan->id,
            'plan_name' => $this->currentPlan->name,
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => $this->currentPlan->price,
            'currency' => 'USD',
            'payment_subscription_id' => 'sub_test123',
            'delivery_type' => 'print',
            'includes_digital_access' => false,
        ]);
    }
}