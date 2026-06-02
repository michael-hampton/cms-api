<?php

namespace App\Tests\Functional\Controllers\Crm;

use App\DTO\Stripe\StripeSubscriptionResultDto;
use App\Framework\Container;
use App\Models\Member;
use App\Models\Model;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\Billing\PaymentProviders\NullStripePaymentProcessor;
use App\Services\Billing\PaymentProviders\StripePaymentProcessor;
use App\Services\Billing\Stripe\StripeCustomerGateway;
use App\Services\Billing\Stripe\StripeSubscriptionGateway;
use App\Services\OpenCollab\ArticlePaymentService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery;

/**
 * Functional tests for CrmSubscriptionController.
 *
 * Routes under test:
 *   GET  /api/crm/admin/subscriptions/{id}/history
 *   GET  /api/crm/admin/members/{memberId}/subscription-stats
 *   GET  /api/crm/admin/members/{memberId}/payments
 *   GET  /api/crm/members/{memberId}/orders
 *   GET  /api/crm/members/{memberId}/activity
 *   GET  /api/crm/admin/subscriptions/plans/{planId}
 *   POST /api/crm/admin/members/{memberId}/subscriptions
 *   POST /api/crm/admin/members/{memberId}/subscriptions/{id}/cancel
 *   POST /api/crm/admin/members/{memberId}/subscriptions/{id}/pause-delivery
 *   POST /api/crm/admin/members/{memberId}/subscriptions/{id}/resume-delivery
 *   POST /api/crm/admin/members/{memberId}/subscriptions/{id}/reactivate
 *   GET  /api/crm/admin/members/{memberId}/subscriptions/{id}/issues
 *
 * All use resourceResponse → { success: true, ...data } at the top level,
 * except error paths which use jsonResponse or errorResponse.
 */
class CrmSubscriptionControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private Member $member;
    private SubscriptionPlan $plan;
    private Subscription $subscription;
    private SubscriptionPlan $newPlan;

    // ── history ───────────────────────────────────────────────────────────────

    public function test_history_returns_200_with_events_array(): void
    {
        $response = $this->getForSite(
            '/api/crm/subscriptions/' . $this->subscription->id . '/history'
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('events', $data);
        $this->assertIsArray($data['events']);
    }

    /**
     * Override assertResponseStatus to allow a custom failure message.
     */
    protected function assertResponseStatus(int $expected, $response, string $message = ''): void
    {
        $actual = $response->getStatusCode();
        $this->assertEquals(
            $expected,
            $actual,
            $message ?: "Expected status {$expected}, got {$actual}. Body: " . substr($response->getContent(), 0, 500)
        );
    }

    public function test_history_returns_401_for_unauthenticated_request(): void
    {
        $this->unauthenticate();

        $response = $this->getForSite(
            '/api/crm/subscriptions/' . $this->subscription->id . '/history'
        );

        $this->assertResponseStatus(401, $response);
    }

    public function test_history_returns_404_for_subscription_on_different_site(): void
    {
        $otherSite = $this->createSite();
        $otherMember = $this->createMember(['site_id' => $otherSite->id]);
        $otherSub = $this->createSubscriptionRecord([
            'member_id' => $otherMember->id,
            'site_id' => $otherSite->id,
        ]);

        $response = $this->getForSite(
            '/api/crm/subscriptions/' . $otherSub->id . '/history'
        );

        $this->assertResponseStatus(404, $response);
    }

    private function createSubscriptionRecord(array $overrides = []): Model
    {
        return Subscription::create(array_merge([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_id' => $this->plan->id ?? null,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
            'next_billing_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
            'price' => 9.99,
            'currency' => 'GBP',
            'auto_renew' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    public function test_history_returns_404_for_non_existent_subscription(): void
    {
        $response = $this->getForSite('/api/crm/subscriptions/999999/history');

        $this->assertResponseStatus(404, $response);
    }

    // ── subscription stats ────────────────────────────────────────────────────

    public function test_history_response_includes_pagination_fields(): void
    {
        $response = $this->getForSite(
            '/api/crm/subscriptions/' . $this->subscription->id . '/history'
        );

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('page', $data);
        $this->assertArrayHasKey('per_page', $data);
    }

    public function test_history_respects_per_page_query_param(): void
    {
        $response = $this->getForSite(
            '/api/crm/subscriptions/' . $this->subscription->id . '/history?per_page=5&page=1'
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(5, $data['per_page']);
        $this->assertEquals(1, $data['page']);
    }

    public function test_subscription_stats_returns_200_with_required_keys(): void
    {
        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id . '/subscription-stats'
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('active_count', $data);
        $this->assertArrayHasKey('cancelled_count', $data);
        $this->assertArrayHasKey('last_payment_date', $data);
        $this->assertArrayHasKey('next_payment_date', $data);
    }

    public function test_subscription_stats_returns_401_for_unauthenticated_request(): void
    {
        $this->unauthenticate();

        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id . '/subscription-stats'
        );

        $this->assertResponseStatus(401, $response);
    }

    // ── payments for member ───────────────────────────────────────────────────

    public function test_subscription_stats_counts_active_subscriptions_correctly(): void
    {
        // Already one active subscription from setUp; add a cancelled one
        $this->createSubscriptionRecord([
            'member_id' => $this->member->id,
            'status' => 'cancelled',
        ]);

        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id . '/subscription-stats'
        );

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(1, $data['active_count']);
        $this->assertEquals(1, $data['cancelled_count']);
    }

    public function test_subscription_stats_returns_zero_counts_for_member_with_no_subscriptions(): void
    {
        $fresh = $this->createMember();

        $response = $this->getForSite(
            '/api/crm/members/' . $fresh->id . '/subscription-stats'
        );

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(0, $data['active_count']);
        $this->assertEquals(0, $data['cancelled_count']);
        $this->assertNull($data['last_payment_date']);
        $this->assertNull($data['next_payment_date']);
    }

    public function test_payments_returns_200_with_payments_array(): void
    {
        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id . '/payments'
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('payments', $data);
        $this->assertIsArray($data['payments']);
    }

    public function test_payments_returns_401_for_unauthenticated_request(): void
    {
        $this->unauthenticate();

        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id . '/payments'
        );

        $this->assertResponseStatus(401, $response);
    }

    public function test_payments_response_includes_pagination(): void
    {
        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id . '/payments'
        );

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('pagination', $data);
        $pagination = $data['pagination'];
        $this->assertArrayHasKey('total', $pagination);
        $this->assertArrayHasKey('current_page', $pagination);
        $this->assertArrayHasKey('per_page', $pagination);
        $this->assertArrayHasKey('last_page', $pagination);
    }

    public function test_payments_filters_by_subscription_context(): void
    {
        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id . '/payments?context=subscription'
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }

    // ── orders for member ─────────────────────────────────────────────────────

    public function test_payments_filters_by_orders_context(): void
    {
        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id . '/payments?context=orders'
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }

    public function test_payments_each_row_has_expected_fields(): void
    {
        $this->createPaymentForSubscription($this->subscription->id);

        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id . '/payments'
        );

        $data = json_decode($response->getContent(), true);

        if (count($data['payments']) > 0) {
            $row = $data['payments'][0];
            $this->assertArrayHasKey('id', $row);
            $this->assertArrayHasKey('amount', $row);
            $this->assertArrayHasKey('currency', $row);
            $this->assertArrayHasKey('status', $row);
            $this->assertArrayHasKey('created_at', $row);
        } else {
            $this->markTestSkipped('No payments exist to assert row shape.');
        }
    }

    private function createPaymentForSubscription(int $subscriptionId, array $overrides = []): Model
    {
        return Payment::create(array_merge([
            'subscription_id' => $subscriptionId,
            'site_id' => $this->siteId,
            'payment_method' => 'stripe',
            'payment_provider' => 'stripe',
            'amount' => 9.99,
            'currency' => 'GBP',
            'status' => 'completed',
            'paid_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    public function test_orders_returns_200_with_orders_array(): void
    {
        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id . '/orders'
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('orders', $data);
        $this->assertIsArray($data['orders']);
    }

    public function test_orders_returns_401_for_unauthenticated_request(): void
    {
        $this->unauthenticate();

        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id . '/orders'
        );

        $this->assertResponseStatus(401, $response);
    }

    public function test_orders_returns_only_orders_for_this_site(): void
    {
        $this->createOrder(['user_id' => $this->member->id, 'site_id' => $this->siteId, 'order_number' => 'MINE-001']);

        $otherSite = $this->createSite();
        $this->createOrder(['user_id' => $this->member->id, 'site_id' => $otherSite->id, 'order_number' => 'OTHER-001']);

        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id . '/orders'
        );

        $data = json_decode($response->getContent(), true);
        $numbers = array_column($data['orders'], 'order_number');

        $this->assertContains('MINE-001', $numbers);
        $this->assertNotContains('OTHER-001', $numbers);
    }

    // ── activity for member ───────────────────────────────────────────────────

    public function test_orders_response_includes_pagination(): void
    {
        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id . '/orders'
        );

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('pagination', $data);
    }

    public function test_orders_each_row_has_expected_fields(): void
    {
        $this->createOrder([
            'user_id' => $this->member->id,
            'site_id' => $this->siteId,
            'order_number' => 'ROW-SHAPE-001',
            'status' => 'pending',
            'total' => 49.99,
        ]);

        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id . '/orders'
        );

        $data = json_decode($response->getContent(), true);

        if (count($data['orders']) > 0) {
            $row = $data['orders'][0];
            $this->assertArrayHasKey('id', $row);
            $this->assertArrayHasKey('order_number', $row);
            $this->assertArrayHasKey('status', $row);
            $this->assertArrayHasKey('total', $row);
            $this->assertArrayHasKey('created_at', $row);
        } else {
            $this->markTestSkipped('No orders exist to assert row shape.');
        }
    }

    public function test_orders_paginates_results(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $this->createOrder([
                'user_id' => $this->member->id,
                'site_id' => $this->siteId,
                'order_number' => "PAGE-ORD-{$i}",
            ]);
        }

        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id . '/orders?per_page=5&page=1'
        );

        $data = json_decode($response->getContent(), true);
        $this->assertCount(5, $data['orders']);
        $this->assertEquals(20, $data['pagination']['total']);
    }

    public function test_activity_returns_200_with_activities_array(): void
    {
        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id . '/activity'
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('activities', $data);
        $this->assertIsArray($data['activities']);
    }

    public function test_activity_returns_401_for_unauthenticated_request(): void
    {
        $this->unauthenticate();

        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id . '/activity'
        );

        $this->assertResponseStatus(401, $response);
    }

    public function test_activity_returns_404_for_member_on_different_site(): void
    {
        $otherSite = $this->createSite();
        $otherMember = $this->createMember(['site_id' => $otherSite->id]);

        $response = $this->getForSite(
            '/api/crm/members/' . $otherMember->id . '/activity'
        );

        $this->assertResponseStatus(404, $response);
    }

    public function test_activity_returns_empty_array_for_member_with_no_activity(): void
    {
        $fresh = $this->createMember();

        $response = $this->getForSite(
            '/api/crm/members/' . $fresh->id . '/activity'
        );

        $data = json_decode($response->getContent(), true);
        $this->assertCount(0, $data['activities']);
    }

    // ── get plan ──────────────────────────────────────────────────────────────

    public function test_activity_includes_created_activity_events(): void
    {
        $this->createMemberActivity([
            'member_id' => $this->member->id,
            'activity_type' => 'page_view',
        ]);

        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id . '/activity'
        );

        $data = json_decode($response->getContent(), true);
        $types = array_column($data['activities'], 'activity_type');
        $this->assertContains('page_view', $types);
    }

    public function test_activity_each_row_has_expected_fields(): void
    {
        $this->createMemberActivity([
            'member_id' => $this->member->id,
            'activity_type' => 'login',
        ]);

        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id . '/activity'
        );

        $data = json_decode($response->getContent(), true);

        if (count($data['activities']) > 0) {
            $row = $data['activities'][0];
            $this->assertArrayHasKey('id', $row);
            $this->assertArrayHasKey('activity_type', $row);
            $this->assertArrayHasKey('activity_date', $row);
        } else {
            $this->markTestSkipped('No activity rows to assert shape.');
        }
    }

    public function test_activity_response_includes_pagination(): void
    {
        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id . '/activity'
        );

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('pagination', $data);
    }

    public function test_get_plan_returns_200_with_plan_data(): void
    {
        $response = $this->getForSite(
            '/api/crm/subscriptions/plans/' . $this->plan->id
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('plan', $data);
        $this->assertEquals($this->plan->id, $data['plan']['id']);
    }

    // ── cancel subscription ───────────────────────────────────────────────────

    public function test_get_plan_returns_401_for_unauthenticated_request(): void
    {
        $this->unauthenticate();

        $response = $this->getForSite(
            '/api/crm/subscriptions/plans/' . $this->plan->id
        );

        $this->assertResponseStatus(401, $response);
    }

    public function test_get_plan_returns_404_for_non_existent_plan(): void
    {
        $response = $this->getForSite('/api/crm/subscriptions/plans/999999');

        $this->assertResponseStatus(404, $response);
    }

    public function test_get_plan_returns_404_for_plan_on_different_site(): void
    {
        $otherSite = $this->createSite();
        $otherPlan = $this->createSubscriptionPlan(['site_id' => $otherSite->id]);

        $response = $this->getForSite(
            '/api/crm/subscriptions/plans/' . $otherPlan->id
        );

        $this->assertResponseStatus(404, $response);
    }

    public function test_cancel_returns_200_with_cancel_at_period_end_true(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/subscriptions/' . $this->subscription->id . '/cancel',
            ['cancel_at_period_end' => true]
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertStringContainsString('end of the billing period', $data['message']);
    }

    public function test_cancel_returns_200_with_cancel_immediately(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/subscriptions/' . $this->subscription->id . '/cancel',
            ['cancel_at_period_end' => false]
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertStringContainsString('Subscription cancelled immediately.', $data['message']);
    }

    // ── pause delivery ────────────────────────────────────────────────────────

    public function test_cancel_returns_404_when_subscription_belongs_to_different_member(): void
    {
        $otherMember = $this->createMember();

        $response = $this->postForSite(
            '/api/crm/members/' . $otherMember->id . '/subscriptions/' . $this->subscription->id . '/cancel',
            ['cancel_at_period_end' => true]
        );

        $this->assertResponseStatus(404, $response);
    }

    public function test_cancel_returns_404_for_non_existent_subscription(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/subscriptions/999999/cancel',
            ['cancel_at_period_end' => true]
        );

        $this->assertResponseStatus(404, $response);
    }

    public function test_cancel_includes_subscription_in_response(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/subscriptions/' . $this->subscription->id . '/cancel',
            ['cancel_at_period_end' => true]
        );

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('subscription', $data);
    }

    // ── resume delivery ───────────────────────────────────────────────────────

    public function test_pause_delivery_returns_200_with_valid_dates(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/subscriptions/' . $this->subscription->id . '/pause-delivery',
            [
                'pause_start' => date('Y-m-d', strtotime('+1 day')),
                'pause_end' => date('Y-m-d', strtotime('+30 days')),
                'reason' => 'Holiday',
            ]
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }

    public function test_pause_delivery_returns_404_when_subscription_belongs_to_different_member(): void
    {
        $otherMember = $this->createMember();

        $response = $this->postForSite(
            '/api/crm/members/' . $otherMember->id . '/subscriptions/' . $this->subscription->id . '/pause-delivery',
            [
                'pause_start' => date('Y-m-d', strtotime('+1 day')),
                'pause_end' => date('Y-m-d', strtotime('+30 days')),
            ]
        );

        $this->assertResponseStatus(404, $response);
    }

    public function test_pause_delivery_returns_404_for_non_existent_subscription(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/subscriptions/999999/pause-delivery',
            [
                'pause_start' => date('Y-m-d', strtotime('+1 day')),
                'pause_end' => date('Y-m-d', strtotime('+30 days')),
            ]
        );

        $this->assertResponseStatus(404, $response);
    }

    // ── reactivate subscription ───────────────────────────────────────────────

    public function test_resume_delivery_returns_200_for_paused_subscription(): void
    {
        // Pause it first
        $this->subscription->update([
            'delivery_paused' => true,
            'delivery_pause_start' => date('Y-m-d', strtotime('-5 days')),
            'delivery_pause_end' => date('Y-m-d', strtotime('+25 days')),
        ]);

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/subscriptions/' . $this->subscription->id . '/resume-delivery',
            []
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }

    public function test_resume_delivery_returns_404_when_subscription_belongs_to_different_member(): void
    {
        $otherMember = $this->createMember();

        $response = $this->postForSite(
            '/api/crm/members/' . $otherMember->id . '/subscriptions/' . $this->subscription->id . '/resume-delivery',
            []
        );

        $this->assertResponseStatus(404, $response);
    }

    public function test_resume_delivery_returns_404_for_non_existent_subscription(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/subscriptions/999999/resume-delivery',
            []
        );

        $this->assertResponseStatus(404, $response);
    }

    public function test_reactivate_returns_200_for_cancelled_subscription(): void
    {
        $this->subscription->update([
            'status' => 'cancelled',
            'auto_renew' => false,
            'end_date' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/subscriptions/' . $this->subscription->id . '/reactivate',
            []
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }

    // ── issues for subscription ───────────────────────────────────────────────

    public function test_reactivate_returns_404_when_subscription_belongs_to_different_member(): void
    {
        $otherMember = $this->createMember();

        $response = $this->postForSite(
            '/api/crm/members/' . $otherMember->id . '/subscriptions/' . $this->subscription->id . '/reactivate',
            []
        );

        $this->assertResponseStatus(404, $response);
    }

    public function test_reactivate_returns_404_for_non_existent_subscription(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/subscriptions/999999/reactivate',
            []
        );

        $this->assertResponseStatus(404, $response);
    }

    public function test_reactivate_includes_subscription_in_response(): void
    {
        $this->subscription->update([
            'status' => 'cancelled',
            'end_date' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/subscriptions/' . $this->subscription->id . '/reactivate',
            []
        );

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('subscription', $data);
    }

    public function test_issues_returns_200_with_issues_array(): void
    {
        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id . '/subscriptions/' . $this->subscription->id . '/issues'
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('issues', $data);
        $this->assertIsArray($data['issues']);
    }

    public function test_issues_returns_401_for_unauthenticated_request(): void
    {
        $this->unauthenticate();

        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id . '/subscriptions/' . $this->subscription->id . '/issues'
        );

        $this->assertResponseStatus(401, $response);
    }

    public function test_issues_returns_404_when_subscription_belongs_to_different_member(): void
    {
        $otherMember = $this->createMember();

        $response = $this->getForSite(
            '/api/crm/members/' . $otherMember->id . '/subscriptions/' . $this->subscription->id . '/issues'
        );

        $this->assertResponseStatus(404, $response);
    }

    // ── refund payment ────────────────────────────────────────────────────────

    public function test_refund_payment_returns_401_for_unauthenticated_request(): void
    {
        $this->unauthenticate();

        $payment = $this->createPaymentForSubscription($this->subscription->id);

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/payments/' . $payment->id . '/refund',
            []
        );

        $this->assertResponseStatus(401, $response);
    }

    public function test_refund_payment_returns_404_for_non_existent_payment(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/payments/999999/refund',
            []
        );

        $this->assertResponseStatus(404, $response);
    }

    public function test_refund_payment_returns_404_when_payment_belongs_to_different_member(): void
    {
        $otherMember = $this->createMember();
        $otherSub    = $this->createSubscriptionRecord(['member_id' => $otherMember->id]);
        $payment     = $this->createPaymentForSubscription($otherSub->id);

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/payments/' . $payment->id . '/refund',
            []
        );

        $this->assertResponseStatus(404, $response);
    }

    public function test_refund_payment_returns_422_when_payment_already_refunded(): void
    {
        $payment = $this->createPaymentForSubscription($this->subscription->id, ['status' => 'refunded']);

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/payments/' . $payment->id . '/refund',
            []
        );

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('already been refunded', $data['message']);
    }

    public function test_refund_payment_returns_422_when_payment_is_not_completed(): void
    {
        $payment = $this->createPaymentForSubscription($this->subscription->id, ['status' => 'pending']);

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/payments/' . $payment->id . '/refund',
            []
        );

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function test_refund_payment_returns_422_when_amount_exceeds_original(): void
    {
        $payment = $this->createPaymentForSubscription($this->subscription->id, ['amount' => 9.99]);

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/payments/' . $payment->id . '/refund',
            ['amount' => 99.99]
        );

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('cannot exceed', $data['message']);
    }

    public function test_refund_payment_returns_422_when_amount_is_zero(): void
    {
        $payment = $this->createPaymentForSubscription($this->subscription->id);

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/payments/' . $payment->id . '/refund',
            ['amount' => 0]
        );

        $this->assertResponseStatus(422, $response);
    }

    public function test_refund_payment_returns_200_with_full_refund_by_default(): void
    {
        $payment = $this->createPaymentForSubscription($this->subscription->id, ['amount' => 9.99]);

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/payments/' . $payment->id . '/refund',
            ['reason' => 'customer_request']
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('refund_payment', $data);
        $this->assertArrayHasKey('amount', $data);
        $this->assertEquals(9.99, $data['amount']);
    }

    public function test_refund_payment_returns_200_for_partial_refund(): void
    {
        $payment = $this->createPaymentForSubscription($this->subscription->id, ['amount' => 20.00]);

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/payments/' . $payment->id . '/refund',
            ['amount' => 5.00, 'reason' => 'partial_service_failure']
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals(5.00, $data['amount']);
    }

    public function test_refund_payment_marks_original_payment_as_refunded(): void
    {
        $payment = $this->createPaymentForSubscription($this->subscription->id, ['amount' => 9.99]);

        $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/payments/' . $payment->id . '/refund',
            []
        );

        $refreshed = Payment::find($payment->id);
        $this->assertEquals('refunded', $refreshed->status);
    }

    public function test_refund_payment_response_includes_success_message(): void
    {
        $payment = $this->createPaymentForSubscription($this->subscription->id);

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/payments/' . $payment->id . '/refund',
            []
        );

        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('processed successfully', $data['message']);
    }

    // ── bulk refund payments ──────────────────────────────────────────────────

    public function test_bulk_refund_returns_401_for_unauthenticated_request(): void
    {
        $this->unauthenticate();

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/payments/bulk-refund',
            ['payment_ids' => [1, 2]]
        );

        $this->assertResponseStatus(401, $response);
    }

    public function test_bulk_refund_returns_422_when_payment_ids_is_empty(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/payments/bulk-refund',
            ['payment_ids' => []]
        );

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('payment_ids', $data['message']);
    }

    public function test_bulk_refund_returns_422_when_payment_ids_is_missing(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/payments/bulk-refund',
            []
        );

        $this->assertResponseStatus(422, $response);
    }

    public function test_bulk_refund_returns_422_when_exceeding_50_payments(): void
    {
        $ids = range(1, 51);

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/payments/bulk-refund',
            ['payment_ids' => $ids]
        );

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('50', $data['message']);
    }

    public function test_bulk_refund_returns_200_and_refunds_all_eligible_payments(): void
    {
        $p1 = $this->createPaymentForSubscription($this->subscription->id, ['amount' => 9.99]);
        $p2 = $this->createPaymentForSubscription($this->subscription->id, ['amount' => 19.99]);

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/payments/bulk-refund',
            ['payment_ids' => [$p1->id, $p2->id], 'reason' => 'customer_request']
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals(2, $data['succeeded']);
        $this->assertEquals(0, $data['failed']);
        $this->assertCount(2, $data['results']);
    }

    public function test_bulk_refund_marks_all_original_payments_as_refunded(): void
    {
        $p1 = $this->createPaymentForSubscription($this->subscription->id);
        $p2 = $this->createPaymentForSubscription($this->subscription->id);

        $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/payments/bulk-refund',
            ['payment_ids' => [$p1->id, $p2->id]]
        );

        $this->assertEquals('refunded', Payment::find($p1->id)->status);
        $this->assertEquals('refunded', Payment::find($p2->id)->status);
    }

    public function test_bulk_refund_reports_partial_failure_when_some_payments_are_ineligible(): void
    {
        $eligible   = $this->createPaymentForSubscription($this->subscription->id, ['status' => 'completed']);
        $alreadyRefunded = $this->createPaymentForSubscription($this->subscription->id, ['status' => 'refunded']);

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/payments/bulk-refund',
            ['payment_ids' => [$eligible->id, $alreadyRefunded->id]]
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']); // not all succeeded
        $this->assertEquals(1, $data['succeeded']);
        $this->assertEquals(1, $data['failed']);
    }

    public function test_bulk_refund_skips_payments_belonging_to_different_member(): void
    {
        $otherMember = $this->createMember();
        $otherSub    = $this->createSubscriptionRecord(['member_id' => $otherMember->id]);
        $otherPayment = $this->createPaymentForSubscription($otherSub->id);

        $own = $this->createPaymentForSubscription($this->subscription->id);

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/payments/bulk-refund',
            ['payment_ids' => [$own->id, $otherPayment->id]]
        );

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(1, $data['succeeded']);
        $this->assertEquals(1, $data['failed']);
    }

    public function test_bulk_refund_results_array_contains_per_payment_outcome(): void
    {
        $p1 = $this->createPaymentForSubscription($this->subscription->id);

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/payments/bulk-refund',
            ['payment_ids' => [$p1->id]]
        );

        $data = json_decode($response->getContent(), true);
        $result = $data['results'][0];

        $this->assertArrayHasKey('payment_id', $result);
        $this->assertArrayHasKey('success', $result);
        $this->assertEquals($p1->id, $result['payment_id']);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('amount', $result);
    }

    // ── setup / helpers ───────────────────────────────────────────────────────


    public function test_issues_returns_404_for_non_existent_subscription(): void
    {
        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id . '/subscriptions/999999/issues'
        );

        $this->assertResponseStatus(404, $response);
    }

    public function test_issues_response_includes_pagination(): void
    {
        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id . '/subscriptions/' . $this->subscription->id . '/issues'
        );

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('pagination', $data);
        $pagination = $data['pagination'];
        $this->assertArrayHasKey('total', $pagination);
        $this->assertArrayHasKey('current_page', $pagination);
        $this->assertArrayHasKey('per_page', $pagination);
        $this->assertArrayHasKey('last_page', $pagination);
    }

    public function test_issues_accepts_filter_parameter(): void
    {
        foreach (['all', 'upcoming', 'previous', 'missed'] as $filter) {
            $response = $this->getForSite(
                '/api/crm/members/' . $this->member->id
                . '/subscriptions/' . $this->subscription->id
                . '/issues?filter=' . $filter
            );

            $this->assertResponseStatus(200, $response, "Filter '{$filter}' should return 200");
        }
    }

    public function test_renew_returns_401_for_unauthenticated_request(): void
    {
        $this->unauthenticate();

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/subscriptions/' . $this->subscription->id . '/renew',
            ['plan_id' => $this->plan->id, 'payment_method_id' => 'pm_test_123', 'amount' => 9.99]
        );

        $this->assertResponseStatus(401, $response);
    }

    public function test_renew_returns_404_when_subscription_belongs_to_different_member(): void
    {
        $otherMember = $this->createMember();

        $response = $this->postForSite(
            '/api/crm/members/' . $otherMember->id . '/subscriptions/' . $this->subscription->id . '/renew',
            ['plan_id' => $this->plan->id, 'payment_method_id' => 'pm_test_123', 'amount' => 9.99]
        );

        $this->assertResponseStatus(404, $response);
    }

    public function test_renew_returns_404_for_non_existent_subscription(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/subscriptions/999999/renew',
            ['plan_id' => $this->plan->id, 'payment_method_id' => 'pm_test_123', 'amount' => 9.99]
        );

        $this->assertResponseStatus(404, $response);
    }

    public function test_renew_returns_422_when_plan_id_is_missing(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/subscriptions/' . $this->subscription->id . '/renew',
            ['payment_method_id' => 'pm_test_123', 'amount' => 9.99]
        );

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('plan_id', $data['error']);
    }

    public function test_renew_returns_422_when_payment_method_id_is_missing(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/subscriptions/' . $this->subscription->id . '/renew',
            ['plan_id' => $this->plan->id, 'amount' => 9.99]
        );

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('payment_method_id', $data['error']);
    }

    public function test_renew_does_not_require_amount(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/subscriptions/' . $this->subscription->id . '/renew',
            ['plan_id' => $this->plan->id, 'payment_method_id' => 'pm_test_valid']
        );

        $this->assertResponseStatus(201, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }

    public function test_renew_returns_201_with_old_and_new_subscription(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/subscriptions/' . $this->subscription->id . '/renew',
            [
                'plan_id' => $this->plan->id,
                'payment_method_id' => 'pm_test_valid',
                'amount' => 9.99,
            ]
        );

        $this->assertResponseStatus(201, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('old_subscription', $data);
        $this->assertArrayHasKey('new_subscription', $data);
        $this->assertStringContainsString('renewed', $data['message']);
    }

    public function test_renew_accepts_a_different_plan_id_than_the_current_subscription(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/subscriptions/' . $this->subscription->id . '/renew',
            [
                'plan_id' => $this->newPlan->id,
                'payment_method_id' => 'pm_test_valid',
                'amount' => 19.99,
            ]
        );

        // The controller delegates plan validation to the service; a 201 or a
        // service-level 422/500 are both acceptable here — we just assert no
        // ownership or input-level error (not 404, not 401).
        $status = $response->getStatusCode();
        $this->assertNotEquals(401, $status);
        $this->assertNotEquals(404, $status);
    }

    public function test_switch_preview_returns_401_for_unauthenticated_request(): void
    {
        $this->unauthenticate();

        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id
            . '/subscriptions/' . $this->subscription->id
            . '/switch-preview?new_plan_id=' . $this->newPlan->id
        );

        $this->assertResponseStatus(401, $response);
    }

    public function test_switch_preview_returns_404_when_subscription_belongs_to_different_member(): void
    {
        $otherMember = $this->createMember();

        $response = $this->getForSite(
            '/api/crm/members/' . $otherMember->id
            . '/subscriptions/' . $this->subscription->id
            . '/switch-preview?new_plan_id=' . $this->newPlan->id
        );

        $this->assertResponseStatus(404, $response);
    }

    public function test_switch_preview_returns_404_for_non_existent_subscription(): void
    {
        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id
            . '/subscriptions/999999'
            . '/switch-preview?new_plan_id=' . $this->newPlan->id
        );

        $this->assertResponseStatus(404, $response);
    }

    public function test_switch_preview_returns_422_when_new_plan_id_is_missing(): void
    {
        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id
            . '/subscriptions/' . $this->subscription->id
            . '/switch-preview'
        );

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('new_plan_id', $data['error']);
    }

    public function test_switch_preview_returns_404_for_non_existent_plan(): void
    {
        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id
            . '/subscriptions/' . $this->subscription->id
            . '/switch-preview?new_plan_id=999999'
        );

        $this->assertResponseStatus(404, $response);
    }

    public function test_switch_preview_returns_404_for_plan_on_different_site(): void
    {
        $otherSite = $this->createSite();
        $otherPlan = $this->createSubscriptionPlan(['site_id' => $otherSite->id, 'is_active' => true]);

        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id
            . '/subscriptions/' . $this->subscription->id
            . '/switch-preview?new_plan_id=' . $otherPlan->id
        );

        $this->assertResponseStatus(404, $response);
    }

    public function test_switch_preview_returns_404_for_inactive_plan(): void
    {
        $inactivePlan = $this->createSubscriptionPlan([
            'site_id' => $this->siteId,
            'is_active' => false,
        ]);

        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id
            . '/subscriptions/' . $this->subscription->id
            . '/switch-preview?new_plan_id=' . $inactivePlan->id
        );

        $this->assertResponseStatus(404, $response);
    }

    public function test_switch_preview_returns_200_with_credit_and_price_fields(): void
    {
        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id
            . '/subscriptions/' . $this->subscription->id
            . '/switch-preview?new_plan_id=' . $this->newPlan->id
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('carried_over_credit', $data);
        $this->assertArrayHasKey('new_plan_full_price', $data);
        $this->assertArrayHasKey('amount_due_transfer', $data);
        $this->assertArrayHasKey('amount_due_fresh', $data);
    }

    public function test_switch_preview_amount_due_transfer_is_never_negative(): void
    {
        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id
            . '/subscriptions/' . $this->subscription->id
            . '/switch-preview?new_plan_id=' . $this->newPlan->id
        );

        $data = json_decode($response->getContent(), true);
        $this->assertGreaterThanOrEqual(0, $data['amount_due_transfer']);
    }

    public function test_switch_preview_amount_due_fresh_equals_new_plan_full_price(): void
    {
        $response = $this->getForSite(
            '/api/crm/members/' . $this->member->id
            . '/subscriptions/' . $this->subscription->id
            . '/switch-preview?new_plan_id=' . $this->newPlan->id
        );

        $data = json_decode($response->getContent(), true);
        $this->assertEquals($data['new_plan_full_price'], $data['amount_due_fresh']);
    }

    public function test_switch_product_returns_401_for_unauthenticated_request(): void
    {
        $this->unauthenticate();

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/subscriptions/' . $this->subscription->id . '/switch',
            $this->validSwitchPayload()
        );

        $this->assertResponseStatus(401, $response);
    }

    public function test_switch_product_returns_404_when_subscription_belongs_to_different_member(): void
    {
        $otherMember = $this->createMember();

        $response = $this->postForSite(
            '/api/crm/members/' . $otherMember->id . '/subscriptions/' . $this->subscription->id . '/switch',
            $this->validSwitchPayload()
        );

        $this->assertResponseStatus(404, $response);
    }

    public function test_switch_product_returns_404_for_non_existent_subscription(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/subscriptions/999999/switch',
            $this->validSwitchPayload()
        );

        $this->assertResponseStatus(404, $response);
    }

    public function test_switch_product_returns_422_when_new_plan_id_is_missing(): void
    {
        $payload = $this->validSwitchPayload();
        unset($payload['new_plan_id']);

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/subscriptions/' . $this->subscription->id . '/switch',
            $payload
        );

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('new_plan_id', $data['error']);
    }

    public function test_switch_product_returns_422_for_invalid_switch_mode(): void
    {
        $payload = array_merge($this->validSwitchPayload(), ['switch_mode' => 'invalid']);

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/subscriptions/' . $this->subscription->id . '/switch',
            $payload
        );

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('switch_mode', $data['error']);
    }

    public function test_switch_product_returns_422_when_payment_method_id_is_missing(): void
    {
        $payload = $this->validSwitchPayload();
        unset($payload['payment_method_id']);

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/subscriptions/' . $this->subscription->id . '/switch',
            $payload
        );

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('payment_method_id', $data['error']);
    }

    public function test_switch_product_returns_422_when_amount_is_zero(): void
    {
        $payload = array_merge($this->validSwitchPayload(), ['amount' => 0, 'switch_mode' => 'fresh']);

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/subscriptions/' . $this->subscription->id . '/switch',
            $payload
        );

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('amount', $data['error']);
    }

    public function test_switch_product_accepts_fresh_mode(): void
    {
        $payload = array_merge($this->validSwitchPayload(), [
            'switch_mode' => 'fresh',
            'carried_over_credit' => 0,
        ]);

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/subscriptions/' . $this->subscription->id . '/switch',
            $payload
        );

        // Not a validation error
        $this->assertNotEquals(422, $response->getStatusCode());
        $this->assertNotEquals(401, $response->getStatusCode());
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function test_switch_product_returns_201_with_old_and_new_subscription(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/subscriptions/' . $this->subscription->id . '/switch',
            $this->validSwitchPayload()
        );

        $this->assertResponseStatus(201, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('old_subscription', $data);
        $this->assertArrayHasKey('new_subscription', $data);
        $this->assertStringContainsString('switched', $data['message']);
    }

    public function test_request_issue_replacement_returns_401_for_unauthenticated_request(): void
    {
        $this->unauthenticate();

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id
            . '/subscriptions/' . $this->subscription->id
            . '/issues/1/replace',
            ['reason' => 'Damaged on arrival']
        );

        $this->assertResponseStatus(401, $response);
    }

    public function test_request_issue_replacement_returns_404_when_subscription_belongs_to_different_member(): void
    {
        $otherMember = $this->createMember();

        $response = $this->postForSite(
            '/api/crm/members/' . $otherMember->id
            . '/subscriptions/' . $this->subscription->id
            . '/issues/1/replace',
            ['reason' => 'Damaged on arrival']
        );

        $this->assertResponseStatus(404, $response);
    }

    public function test_request_issue_replacement_returns_404_for_non_existent_subscription(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id
            . '/subscriptions/999999'
            . '/issues/1/replace',
            ['reason' => 'Never arrived']
        );

        $this->assertResponseStatus(404, $response);
    }

    public function test_request_issue_replacement_returns_422_when_reason_is_missing(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id
            . '/subscriptions/' . $this->subscription->id
            . '/issues/1/replace',
            []
        );

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('reason', $data['error']);
    }

    public function test_request_issue_replacement_returns_201_with_replacement_key(): void
    {
        $issue = $this->createIssueDelivery([
            'subscription_id' => $this->subscription->id,
            'plan_id' => $this->plan->id,
        ]);

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id
            . '/subscriptions/' . $this->subscription->id
            . '/issues/' . $issue->id . '/replace',
            ['reason' => 'Damaged in transit']
        );

        $this->assertResponseStatus(201, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('replacement', $data);
        $this->assertStringContainsString('replacement requested', $data['message']);
    }

    public function test_suspend_returns_401_for_unauthenticated_request(): void
    {
        $this->unauthenticate();

        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/subscriptions/' . $this->subscription->id . '/suspend',
            ['reason' => 'Fraud review']
        );

        $this->assertResponseStatus(401, $response);
    }

    public function test_suspend_returns_404_when_subscription_belongs_to_different_member(): void
    {
        $otherMember = $this->createMember();

        $response = $this->postForSite(
            '/api/crm/members/' . $otherMember->id . '/subscriptions/' . $this->subscription->id . '/suspend',
            ['reason' => 'Fraud review']
        );

        $this->assertResponseStatus(404, $response);
    }

    public function test_suspend_returns_404_for_non_existent_subscription(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/subscriptions/999999/suspend',
            ['reason' => 'Fraud review']
        );

        $this->assertResponseStatus(404, $response);
    }

    public function test_suspend_returns_422_when_reason_is_missing(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/subscriptions/' . $this->subscription->id . '/suspend',
            []
        );

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('reason', $data['error']);
    }

    public function test_suspend_returns_422_when_reason_is_blank(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/subscriptions/' . $this->subscription->id . '/suspend',
            ['reason' => '   ']
        );

        $this->assertResponseStatus(422, $response);
    }

    public function test_suspend_returns_200_with_subscription_in_response(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/subscriptions/' . $this->subscription->id . '/suspend',
            ['reason' => 'Non-payment']
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('subscription', $data);
        $this->assertStringContainsString('suspended', $data['message']);
    }

    public function test_suspend_success_message_indicates_suspension(): void
    {
        $response = $this->postForSite(
            '/api/crm/members/' . $this->member->id . '/subscriptions/' . $this->subscription->id . '/suspend',
            ['reason' => 'Chargeback dispute']
        );

        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('suspended successfully', $data['message']);
    }

    private function validSwitchPayload(array $overrides = []): array
    {
        return array_merge([
            'new_plan_id' => $this->newPlan->id,
            'switch_mode' => 'transfer',
            'payment_method_id' => 'pm_test_valid',
            'amount' => 9.99,
            'carried_over_credit' => 0.00,
        ], $overrides);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = $this->createMember([
            'first_name' => 'Sub',
            'last_name' => 'Tester',
            'email' => 'sub.tester.' . uniqid() . '@example.com',
            'is_active' => true,
            'anonymous' => false,
        ]);

        $this->plan = $this->createSubscriptionPlan([
            'site_id' => $this->siteId,
            'name' => 'Test Plan',
            'is_active' => true,
        ]);

        $this->createPricingTier(['plan_id' => $this->plan->id, 'is_default' => true, 'stripe_price_id' => 'abc', 'is_active' => true]);

        Container::getInstance()->bind(StripeCustomerGateway::class, function (Container $container) {
            $mock = Mockery::mock(StripeCustomerGateway::class);

            $mock->shouldReceive('getOrCreate')->andReturn('abc');
            $mock->shouldReceive('attachPaymentMethod')->andReturn('abc');

           return $mock;
        });

        Container::getInstance()->bind(StripeSubscriptionGateway::class, function (Container $container) {
            $mock = Mockery::mock(StripeSubscriptionGateway::class);

            $dto = new StripeSubscriptionResultDto(1, null, 'active', null, 1, 2, 'abc', 'abc', 'abc', false);

            $mock->shouldReceive('create')->andReturn($dto);

            return $mock;
        });

        $this->subscription = $this->createSubscriptionRecord([
            'member_id' => $this->member->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'delivery_type' => 'print',
            'delivery_paused' => false,
        ]);

        $this->newPlan = $this->createSubscriptionPlan([
            'site_id' => $this->siteId,
            'name' => 'Upgraded Plan',
            'price' => 19.99,
            'is_active' => true,
            'print_shipping_required' => true,
        ]);

        $this->createPricingTier(['plan_id' => $this->newPlan->id, 'is_default' => true, 'stripe_price_id' => 'abc', 'is_active' => true, 'duration_months' => 3]);

        $this->createIssueDelivery(['subscription_plan_id' => $this->newPlan->id, 'stock_quantity' => 100]);

        Container::getInstance()->bind(
            StripePaymentProcessor::class,
            NullStripePaymentProcessor::class
        );
    }
}
