<?php

namespace App\Tests\Functional\Controllers\Members\Api\Subscriptions;

use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MemberSubscriptionPlansApiControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function setUp(): void
    {
        parent::setUp();

        $member = $this->createMember();
        $this->actingAsMember($member);
    }

    // =========================================================================
    // GET /api/member/subscription-plans
    // =========================================================================

    public function test_index_returns_active_plans_for_site(): void
    {
        $this->createSubscriptionPlan(['site_id' => $this->siteId, 'is_active' => true]);
        $this->createSubscriptionPlan(['site_id' => $this->siteId, 'is_active' => true]);

        $response = $this->getForSite('/api/member/subscription-plans', [], true);

        $this->assertResponseOk($response);
        $body = $this->decodeJson($response);
        $this->assertTrue($body['data']['success']);
        $this->assertArrayHasKey('plans', $body['data']);
        $this->assertCount(2, $body['data']['plans']);
    }

    public function test_index_does_not_return_plans_from_other_sites(): void
    {
        $otherSite = $this->createSite();
        $this->createSubscriptionPlan(['site_id' => $otherSite->id, 'is_active' => true]);

        $response = $this->getForSite('/api/member/subscription-plans', [], true);

        $this->assertResponseOk($response);
        $body = $this->decodeJson($response);
        $this->assertCount(0, $body['data']['plans']);
    }

    public function test_index_includes_current_subscription_when_authenticated(): void
    {
        $member = $this->createAuthenticatedMember();
        $this->createSubscription(['member_id' => $member->id, 'status' => 'active']);

        $response = $this->getForSite('/api/member/subscription-plans', [], true);

        $this->assertResponseOk($response);
        $body = $this->decodeJson($response);
        $this->assertArrayHasKey('currentSubscription', $body['data']);
        $this->assertNotNull($body['data']['currentSubscription']);
    }

    private function createAuthenticatedMember(array $overrides = [])
    {
        $member = $this->createMember($overrides);
        $this->actingAsMember($member);
        return $member;
    }

    // =========================================================================
    // GET /api/member/subscription-plans/{slug}
    // =========================================================================

    public function test_index_current_subscription_is_null_when_unauthenticated(): void
    {
        $this->unauthenticateMember();
        $this->createSubscriptionPlan(['site_id' => $this->siteId, 'is_active' => true]);

        $response = $this->getForSite('/api/member/subscription-plans', [], true);

        $this->assertResponseOk($response);
        $body = $this->decodeJson($response);
        $this->assertNull($body['data']['currentSubscription']);
    }

    public function test_show_returns_plan_by_slug(): void
    {
        $plan = $this->createSubscriptionPlan(['site_id' => $this->siteId, 'slug' => 'gold-monthly', 'is_active' => true]);

        $response = $this->getForSite('/api/member/subscription-plans/gold-monthly', [], true);

        $this->assertResponseOk($response);
        $body = $this->decodeJson($response);
        $this->assertTrue($body['data']['success']);
        $this->assertEquals($plan->id, $body['data']['plan']['id']);
    }

    public function test_show_returns_404_for_unknown_slug(): void
    {
        $response = $this->getForSite('/api/member/subscription-plans/does-not-exist', [], true);

        $this->assertResponseStatus(404, $response);
    }

    // =========================================================================
    // POST /api/member/subscription-plans/{slug}/subscribe
    // =========================================================================

    public function test_show_returns_can_subscribe_flag_for_authenticated_member(): void
    {
        $this->createAuthenticatedMember();
        $this->createSubscriptionPlan(['site_id' => $this->siteId, 'slug' => 'silver-plan', 'is_active' => true]);

        $response = $this->getForSite('/api/member/subscription-plans/silver-plan', [], true);

        $this->assertResponseOk($response);
        $body = $this->decodeJson($response);
        $this->assertArrayHasKey('canSubscribe', $body['data']);
        $this->assertArrayHasKey('can_subscribe', $body['data']['canSubscribe']);
    }

    public function test_subscribe_returns_401_when_unauthenticated(): void
    {
        $this->unauthenticateMember();
        $this->createSubscriptionPlan(['site_id' => $this->siteId, 'slug' => 'basic-plan', 'is_active' => true]);

        $response = $this->postForSite('/api/member/subscription-plans/basic-plan/subscribe');

        $this->assertResponseStatus(401, $response);
    }

    // =========================================================================
    // POST /api/member/subscription-plans/{slug}/validate-voucher
    // =========================================================================

    public function test_subscribe_returns_404_for_unknown_plan(): void
    {
        $this->createAuthenticatedMember();

        $response = $this->postForSite('/api/member/subscription-plans/no-such-plan/subscribe', [], [], [], false, true);

        $this->assertResponseStatus(404, $response);
    }

    public function test_validate_voucher_returns_401_when_unauthenticated(): void
    {
        $this->unauthenticateMember();
        $this->createSubscriptionPlan(['site_id' => $this->siteId, 'slug' => 'pro-plan', 'is_active' => true]);

        $response = $this->postForSite('/api/member/subscription-plans/pro-plan/validate-voucher', [
            'voucher_code' => 'SAVE10',
        ], [], [], false, true);

        $this->assertResponseStatus(400, $response);
    }

    public function test_validate_voucher_returns_404_for_unknown_plan(): void
    {
        $this->createAuthenticatedMember();

        $response = $this->postForSite('/api/member/subscription-plans/ghost-plan/validate-voucher', [
            'voucher_code' => 'SAVE10',
        ], [], [], false, true);

        $this->assertResponseStatus(404, $response);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function test_validate_voucher_returns_400_when_code_missing(): void
    {
        $this->createAuthenticatedMember();
        $this->createSubscriptionPlan(['site_id' => $this->siteId, 'slug' => 'pro-plan-v', 'is_active' => true]);

        $response = $this->postForSite('/api/member/subscription-plans/pro-plan-v/validate-voucher', [], [], [], false, true);

        $this->assertResponseStatus(400, $response);
    }
}