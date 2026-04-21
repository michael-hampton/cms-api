<?php

namespace App\Tests\Functional\Controllers\Members\Api\Subscriptions;

use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MemberSubscriptionsApiControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function setUp(): void
    {
        parent::setUp();

        $member = $this->createMember();
        $this->actingAsMember($member);
    }

    // =========================================================================
    // GET /api/member/subscriptions/overview
    // =========================================================================

    public function test_overview_returns_subscription_data_for_authenticated_member(): void
    {
        $member = $this->createAuthenticatedMember();
        $this->createSubscription(['member_id' => $member->id, 'status' => 'active']);

        $response = $this->getForSite('/api/member/subscriptions/overview', [], true);

        $this->assertResponseOk($response);
        $body = $this->decodeJson($response);
        $this->assertTrue($body['success']);
        $this->assertArrayHasKey('activeSubscription', $body);
        $this->assertArrayHasKey('subscriptionHistory', $body);
        $this->assertArrayHasKey('subscriptionSummary', $body);
        $this->assertArrayHasKey('plans', $body);
    }

    private function createAuthenticatedMember(array $overrides = [])
    {
        $member = $this->createMember($overrides);
        $this->actingAsMember($member);
        return $member;
    }

    public function test_overview_returns_401_when_unauthenticated(): void
    {
        $this->unauthenticateMember();

        $response = $this->getForSite('/api/member/subscriptions/overview', [], false);

        $this->assertResponseStatus(401, $response);
    }

    // =========================================================================
    // POST /api/member/subscriptions/{id}/cancel
    // =========================================================================

    public function test_overview_returns_null_active_subscription_when_none_exists(): void
    {
        $this->createAuthenticatedMember();

        $response = $this->getForSite('/api/member/subscriptions/overview', [], true);

        $this->assertResponseOk($response);
        $body = $this->decodeJson($response);
        $this->assertNull($body['activeSubscription']);
    }

    public function test_cancel_cancels_own_subscription(): void
    {
        $member = $this->createAuthenticatedMember();
        $sub = $this->createSubscription(['member_id' => $member->id, 'status' => 'active']);

        $response = $this->postForSite("/api/member/subscriptions/{$sub->id}/cancel", [
            'cancel_at_period_end' => true,
        ], [], [], false, true);

        $this->assertResponseOk($response);
        $body = $this->decodeJson($response);
        $this->assertTrue($body['success']);
    }

    public function test_cancel_returns_404_for_another_members_subscription(): void
    {
        $this->createAuthenticatedMember();
        $other = $this->createMember();
        $sub = $this->createSubscription(['member_id' => $other->id, 'status' => 'active']);

        $response = $this->postForSite("/api/member/subscriptions/{$sub->id}/cancel", [
            'cancel_at_period_end' => true,
        ], [], [], false, true);

        $this->assertResponseStatus(404, $response);
    }

    public function test_cancel_returns_404_for_nonexistent_subscription(): void
    {
        $this->createAuthenticatedMember();

        $response = $this->postForSite('/api/member/subscriptions/99999/cancel', [
            'cancel_at_period_end' => true,
        ], [], [], false, true);

        $this->assertResponseStatus(404, $response);
    }

    // =========================================================================
    // POST /api/member/subscriptions/{id}/reactivate
    // =========================================================================

    public function test_cancel_returns_401_when_unauthenticated(): void
    {
        $this->unauthenticateMember();
        $member = $this->createMember();
        $sub = $this->createSubscription(['member_id' => $member->id, 'status' => 'active']);

        $response = $this->postForSite("/api/member/subscriptions/{$sub->id}/cancel", [], [], [], false, false);

        $this->assertResponseStatus(401, $response);
    }

    public function test_reactivate_returns_404_for_another_members_subscription(): void
    {
        $this->createAuthenticatedMember();
        $other = $this->createMember();
        $sub = $this->createSubscription(['member_id' => $other->id, 'status' => 'cancelled']);

        $response = $this->postForSite("/api/member/subscriptions/{$sub->id}/reactivate", [], [], [], false, true);

        $this->assertResponseStatus(404, $response);
    }

    public function test_reactivate_returns_401_when_unauthenticated(): void
    {
        $this->unauthenticateMember();
        $member = $this->createMember();
        $sub = $this->createSubscription(['member_id' => $member->id]);

        $response = $this->postForSite("/api/member/subscriptions/{$sub->id}/reactivate", [], [], [], false, false);

        $this->assertResponseStatus(401, $response);
    }

    // =========================================================================
    // POST /api/member/subscriptions/{id}/auto-renew
    // =========================================================================

    public function test_reactivate_returns_404_for_nonexistent_subscription(): void
    {
        $this->createAuthenticatedMember();

        $response = $this->postForSite('/api/member/subscriptions/99999/reactivate', [], [], [], false, true);

        $this->assertResponseStatus(404, $response);
    }

    // =========================================================================
    // POST /api/member/subscriptions/{id}/pause-delivery
    // =========================================================================

    public function test_auto_renew_returns_401_when_unauthenticated(): void
    {
        $this->unauthenticateMember();

        $response = $this->postForSite('/api/member/subscriptions/1/auto-renew', [
            'auto_renew' => true,
        ], [], [], false, false);

        $this->assertResponseStatus(401, $response);
    }

    public function test_pause_delivery_returns_404_for_another_members_subscription(): void
    {
        $this->createAuthenticatedMember();
        $other = $this->createMember();
        $sub = $this->createSubscription(['member_id' => $other->id, 'status' => 'active']);

        $response = $this->postForSite("/api/member/subscriptions/{$sub->id}/pause-delivery", [
            'pause_start' => date('Y-m-d', strtotime('+1 day')),
            'pause_end' => date('Y-m-d', strtotime('+14 days')),
        ], [], [], false, true);

        $this->assertResponseStatus(404, $response);
    }

    // =========================================================================
    // POST /api/member/subscriptions/{id}/resume-delivery
    // =========================================================================

    public function test_pause_delivery_returns_401_when_unauthenticated(): void
    {
        $this->unauthenticateMember();
        $member = $this->createMember();
        $sub = $this->createSubscription(['member_id' => $member->id]);

        $response = $this->postForSite("/api/member/subscriptions/{$sub->id}/pause-delivery", [], [], [], false, false);

        $this->assertResponseStatus(401, $response);
    }

    public function test_resume_delivery_returns_404_for_another_members_subscription(): void
    {
        $this->createAuthenticatedMember();
        $other = $this->createMember();
        $sub = $this->createSubscription(['member_id' => $other->id]);

        $response = $this->postForSite("/api/member/subscriptions/{$sub->id}/resume-delivery", [], [], [], false, true);

        $this->assertResponseStatus(404, $response);
    }

    // =========================================================================
    // POST /api/member/subscriptions/{id}/update-billing-date
    // =========================================================================

    public function test_resume_delivery_returns_401_when_unauthenticated(): void
    {
        $this->unauthenticateMember();
        $member = $this->createMember();
        $sub = $this->createSubscription(['member_id' => $member->id]);

        $response = $this->postForSite("/api/member/subscriptions/{$sub->id}/resume-delivery", [], [], [], false, false);

        $this->assertResponseStatus(401, $response);
    }

    public function test_update_billing_date_returns_400_for_invalid_day(): void
    {
        $member = $this->createAuthenticatedMember();
        $sub = $this->createSubscription(['member_id' => $member->id, 'status' => 'active']);

        $response = $this->postForSite("/api/member/subscriptions/{$sub->id}/update-billing-date", [
            'day_of_month' => 0,
        ], [], [], false, true);

        $this->assertResponseStatus(400, $response);
    }

    public function test_update_billing_date_returns_404_for_another_members_subscription(): void
    {
        $this->createAuthenticatedMember();
        $other = $this->createMember();
        $sub = $this->createSubscription(['member_id' => $other->id]);

        $response = $this->postForSite("/api/member/subscriptions/{$sub->id}/update-billing-date", [
            'day_of_month' => 15,
        ], [], [], false, true);

        $this->assertResponseStatus(404, $response);
    }

    // =========================================================================
    // POST /api/member/subscriptions/{id}/preview-billing-change
    // =========================================================================

    public function test_update_billing_date_returns_401_when_unauthenticated(): void
    {
        $this->unauthenticateMember();
        $member = $this->createMember();
        $sub = $this->createSubscription(['member_id' => $member->id]);

        $response = $this->postForSite("/api/member/subscriptions/{$sub->id}/update-billing-date", [
            'day_of_month' => 15,
        ], [], [], false, false);

        $this->assertResponseStatus(401, $response);
    }

    public function test_preview_billing_change_returns_400_for_invalid_day(): void
    {
        $member = $this->createAuthenticatedMember();
        $sub = $this->createSubscription(['member_id' => $member->id, 'status' => 'active']);

        $response = $this->postForSite("/api/member/subscriptions/{$sub->id}/preview-billing-change", [
            'day_of_month' => 32,
        ], [], [], false, true);

        $this->assertResponseStatus(400, $response);
    }

    public function test_preview_billing_change_returns_404_for_another_members_subscription(): void
    {
        $this->createAuthenticatedMember();
        $other = $this->createMember();
        $sub = $this->createSubscription(['member_id' => $other->id]);

        $response = $this->postForSite("/api/member/subscriptions/{$sub->id}/preview-billing-change", [
            'day_of_month' => 15,
        ], [], [], false, true);

        $this->assertResponseStatus(404, $response);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function test_preview_billing_change_returns_401_when_unauthenticated(): void
    {
        $this->unauthenticateMember();
        $member = $this->createMember();
        $sub = $this->createSubscription(['member_id' => $member->id]);

        $response = $this->postForSite("/api/member/subscriptions/{$sub->id}/preview-billing-change", [
            'day_of_month' => 15,
        ], [], [], false, false);

        $this->assertResponseStatus(401, $response);
    }
}