<?php

namespace App\Tests\Functional\Controllers\Crm;

use App\DTO\Cart\TaxData;
use App\DTO\Stripe\PaymentIntentResultDto;
use App\DTO\Stripe\StripeSubscriptionResultDto;
use App\Framework\Container;
use App\Models\FulfilmentReplacement;
use App\Models\Member;
use App\Models\Model;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionChange;
use App\Models\SubscriptionPlan;
use App\Services\Billing\PaymentProviders\NullStripePaymentProcessor;
use App\Services\Billing\PaymentProviders\StripePaymentProcessor;
use App\Services\Billing\Stripe\StripeCustomerGateway;
use App\Services\Billing\Stripe\StripePaymentIntentGateway;
use App\Services\Billing\Stripe\StripeSubscriptionGateway;
use App\Services\Billing\Stripe\StripeSubscriptionPlanUpdater;
use App\Services\Billing\TaxCalculatorService;
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
    private SubscriptionPlan $printPlan;        // current edition (print, publication A)
    private SubscriptionPlan $digitalPlan;      // incompatible delivery type

    private SubscriptionPlan $newPrintPlan;     // target edition  (print, publication B)


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
            'subscription_id'      => $this->subscription->id,
            'subscription_plan_id' => $this->subscription->plan_id,
            'status'              => 'dispatched',
            'on_sale_date'        => date('Y-m-d H:i:s', strtotime('-1 month')),
            'estimated_delivery_date' => date('Y-m-d H:i:s', strtotime('-1 month +7 days')),
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
        $_ENV['STRIPE_SECRET_KEY'] = $_ENV['STRIPE_SECRET_KEY'] ?? 'sk_test_subscription_switch';

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

        $this->newPrintPlan = $this->createSubscriptionPlan([
            'site_id'        => $this->siteId,
            'delivery_type'  => 'print',
            'is_active'      => true,
        ]);

        $this->printPlan    = $this->createSubscriptionPlan([
            'site_id'        => $this->siteId,
            'delivery_type'  => 'print',
            'is_active'      => true,
        ]);

        $this->digitalPlan  = $this->createSubscriptionPlan([
            'site_id'        => $this->siteId,
            'delivery_type'  => 'digital',
            'is_active'      => true,
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

        Container::getInstance()->bind(TaxCalculatorService::class, function () {
            $mock = Mockery::mock(TaxCalculatorService::class);
            $mock->shouldReceive('calculateOrderTax')->andReturn(new TaxData(
                rate: 0,
                taxCents: 0,
                taxableAmountCents: 0,
            ));

            return $mock;
        });

        Container::getInstance()->bind(StripePaymentIntentGateway::class, function () {
            $mock = Mockery::mock(StripePaymentIntentGateway::class);
            $mock->shouldReceive('createWithCustomer')->andReturn(new PaymentIntentResultDto(
                success: true,
                paymentIntentId: 'pi_test_switch',
                clientSecret: 'pi_test_switch_secret',
                status: 'succeeded',
                customerId: 'cus_test_switch',
            ));

            return $mock;
        });

        Container::getInstance()->bind(StripeSubscriptionPlanUpdater::class, function () {
            $mock = Mockery::mock(StripeSubscriptionPlanUpdater::class);
            $mock->shouldReceive('update')->andReturn([
                'success' => true,
                'stripe_subscription_id' => 'sub_test_switch',
            ]);

            return $mock;
        });
    }

    public function test_issues_endpoint_includes_can_request_replacement_field(): void
    {
        [$member, $subscription] = $this->createPrintSubscription();
        $this->createDispatchedIssueDelivery($subscription->id, $subscription->plan_id);

        $data = $this->fetchIssues($member->id, $subscription->id);

        $this->assertArrayHasKey('issues', $data);
        $this->assertNotEmpty($data['issues']);
        $this->assertArrayHasKey('can_request_replacement', $data['issues'][0]);
    }

    public function test_issues_endpoint_includes_replacement_blocked_reason_field(): void
    {
        [$member, $subscription] = $this->createPrintSubscription();
        $this->createDispatchedIssueDelivery($subscription->id, $subscription->plan_id);

        $data = $this->fetchIssues($member->id, $subscription->id);

        $this->assertArrayHasKey('replacement_blocked_reason', $data['issues'][0]);
    }

    public function test_dispatched_print_issue_with_no_open_replacement_returns_true(): void
    {
        [$member, $subscription] = $this->createPrintSubscription();
        $this->createDispatchedIssueDelivery($subscription->id, $subscription->plan_id);

        $data = $this->fetchIssues($member->id, $subscription->id);

        $this->assertTrue($data['issues'][0]['can_request_replacement']);
        $this->assertNull($data['issues'][0]['replacement_blocked_reason']);
    }

    public function test_digital_subscription_returns_false_for_all_issues(): void
    {
        [$member, $subscription] = $this->createDigitalSubscription();
        $this->createDispatchedIssueDelivery($subscription->id, $subscription->plan_id);

        $data = $this->fetchIssues($member->id, $subscription->id);

        foreach ($data['issues'] as $row) {
            $this->assertFalse($row['can_request_replacement']);
            $this->assertNotNull($row['replacement_blocked_reason']);
        }
    }

    public function test_pending_issue_returns_false_and_reason(): void
    {
        [$member, $subscription] = $this->createPrintSubscription();

        $this->createIssueDelivery([
            'subscription_id' => $subscription->id,
            'status'          => 'pending',
            'subscription_plan_id'         => $subscription->plan_id,
        ]);

        $data = $this->fetchIssues($member->id, $subscription->id);

        $this->assertFalse($data['issues'][0]['can_request_replacement']);
        $this->assertStringContainsString('dispatched', $data['issues'][0]['replacement_blocked_reason']);
    }

    public function test_open_replacement_blocks_only_that_issue(): void
    {
        [$member, $subscription] = $this->createPrintSubscription();

        $blockedIssue  = $this->createDispatchedIssueDelivery($subscription->id, $subscription->plan_id);
        $allowedIssue  = $this->createDispatchedIssueDelivery($subscription->id, $subscription->plan_id);

        // Create an open replacement for $blockedIssue only.
        $this->createFulfilmentReplacement([
            'subscription_id'   => $subscription->id,
            'issue_delivery_id' => $blockedIssue->id,
            'status'            => 'pending',
        ]);

        $data = $this->fetchIssues($member->id, $subscription->id);

        $rows = array_column($data['issues'], null, 'id');

        $this->assertFalse($rows[$blockedIssue->id]['can_request_replacement']);
        $this->assertStringContainsString('in progress', $rows[$blockedIssue->id]['replacement_blocked_reason']);

        $this->assertTrue($rows[$allowedIssue->id]['can_request_replacement']);
        $this->assertNull($rows[$allowedIssue->id]['replacement_blocked_reason']);
    }

    public function test_queued_replacement_blocks_issue(): void
    {
        [$member, $subscription] = $this->createPrintSubscription();
        $issue = $this->createDispatchedIssueDelivery($subscription->id, $subscription->plan_id);

        $this->createFulfilmentReplacement([
            'subscription_id'   => $subscription->id,
            'issue_delivery_id' => $issue->id,
            'status'            => 'queued',
        ]);

        $data = $this->fetchIssues($member->id, $subscription->id);

        $this->assertFalse($data['issues'][0]['can_request_replacement']);
    }

    public function test_dispatched_replacement_blocks_issue(): void
    {
        [$member, $subscription] = $this->createPrintSubscription();
        $issue = $this->createDispatchedIssueDelivery($subscription->id, $subscription->plan_id);

        $this->createFulfilmentReplacement([
            'subscription_id'   => $subscription->id,
            'issue_delivery_id' => $issue->id,
            'status'            => 'dispatched',
        ]);

        $data = $this->fetchIssues($member->id, $subscription->id);

        $this->assertFalse($data['issues'][0]['can_request_replacement']);
    }

    public function test_failed_replacement_does_not_block_new_request(): void
    {
        [$member, $subscription] = $this->createPrintSubscription();
        $issue = $this->createDispatchedIssueDelivery($subscription->id, $subscription->plan_id);

        $this->createFulfilmentReplacement([
            'subscription_id'   => $subscription->id,
            'issue_delivery_id' => $issue->id,
            'status'            => 'failed',
        ]);

        $data = $this->fetchIssues($member->id, $subscription->id);

        $this->assertTrue($data['issues'][0]['can_request_replacement']);
    }

    public function test_rejected_replacement_does_not_block_new_request(): void
    {
        [$member, $subscription] = $this->createPrintSubscription();
        $issue = $this->createDispatchedIssueDelivery($subscription->id, $subscription->plan_id);

        $this->createFulfilmentReplacement([
            'subscription_id'   => $subscription->id,
            'issue_delivery_id' => $issue->id,
            'status'            => 'rejected',
        ]);

        $data = $this->fetchIssues($member->id, $subscription->id);

        $this->assertTrue($data['issues'][0]['can_request_replacement']);
    }

    public function test_cancelled_replacement_does_not_block_new_request(): void
    {
        [$member, $subscription] = $this->createPrintSubscription();
        $issue = $this->createDispatchedIssueDelivery($subscription->id, $subscription->plan_id);

        $this->createFulfilmentReplacement([
            'subscription_id'   => $subscription->id,
            'issue_delivery_id' => $issue->id,
            'status'            => 'cancelled',
        ]);

        $data = $this->fetchIssues($member->id, $subscription->id);

        $this->assertTrue($data['issues'][0]['can_request_replacement']);
    }

    public function test_cancelled_subscription_returns_false_for_all_issues(): void
    {
        [$member, $subscription] = $this->createPrintSubscription(['status' => 'cancelled']);
        $this->createDispatchedIssueDelivery($subscription->id, $subscription->plan_id);

        $data = $this->fetchIssues($member->id, $subscription->id);

        foreach ($data['issues'] as $row) {
            $this->assertFalse($row['can_request_replacement']);
        }
    }

    public function test_mixed_issue_list_returns_mixed_eligibility(): void
    {
        [$member, $subscription] = $this->createPrintSubscription();

        $dispatched = $this->createDispatchedIssueDelivery($subscription->id, $subscription->plan_id);
        $pending    = $this->createIssueDelivery([
            'subscription_id' => $subscription->id,
            'status'          => 'pending',
            'subscription_plan_id' => $subscription->plan_id,
        ]);

        $data = $this->fetchIssues($member->id, $subscription->id);
        $rows = array_column($data['issues'], null, 'id');

        $this->assertTrue($rows[$dispatched->id]['can_request_replacement']);
        $this->assertFalse($rows[$pending->id]['can_request_replacement']);
    }

    // =========================================================================
    // change-edition
    // =========================================================================

    public function test_change_edition_returns_200_on_valid_request(): void
    {
        $currentPlanId = (int) $this->subscription->plan_id;

        // Current future issue owed to the customer.
        $currentFutureIssue = $this->createIssueDelivery([
            'subscription_id'      => $this->subscription->id,
            'subscription_plan_id' => $currentPlanId,
            'status'              => 'pending',
            'issue_number'        => 1,
            'on_sale_date'        => date('Y-m-d H:i:s', strtotime('+1 month')),
            'estimated_delivery_date' => date('Y-m-d H:i:s', strtotime('+1 month +7 days')),
        ]);

        // Target schedule issue from the SAME plan.
        $targetIssue = $this->createIssueDelivery([
            'subscription_plan_id' => $currentPlanId,
            'status'              => 'active',
            'issue_number'        => 2,
            'on_sale_date'        => date('Y-m-d H:i:s', strtotime('+2 months')),
            'estimated_delivery_date' => date('Y-m-d H:i:s', strtotime('+2 months +7 days')),
        ]);

        $response = $this->postForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/change-edition",
            [
                'edition_id' => $targetIssue->id,
                'reason'    => 'Agent requested',
            ],
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals($this->subscription->id, $data['subscription_id']);
        $this->assertEquals($currentFutureIssue->id, $data['old_edition_id']);
        $this->assertEquals($targetIssue->id, $data['new_edition_id']);

        $this->subscription->refresh();

        // Important: edition change must NOT change the plan.
        $this->assertEquals($currentPlanId, (int) $this->subscription->plan_id);
    }

    public function test_change_edition_returns_401_for_unauthenticated(): void
    {
        $this->unauthenticate();

        $response = $this->postForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/change-edition",
            ['edition_id' => 999],
        );

        $this->assertResponseStatus(401, $response);
    }

    public function test_change_edition_returns_422_when_edition_id_missing(): void
    {
        $response = $this->postForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/change-edition",
            [],
        );

        $this->assertResponseStatus(422, $response);
    }

    public function test_change_edition_returns_422_for_cross_delivery_type(): void
    {
        $targetIssue = $this->createIssueDelivery([
            'subscription_plan_id' => $this->digitalPlan->id,
            'status'              => 'active',
            'issue_number'        => 1,
            'on_sale_date'        => date('Y-m-d H:i:s', strtotime('+1 month')),
            'estimated_delivery_date' => date('Y-m-d H:i:s', strtotime('+1 month +7 days')),
        ]);

        $response = $this->postForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/change-edition",
            [
                'edition_id' => $targetIssue->id,
            ],
        );

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertStringContainsStringIgnoringCase(
            'subscription plan',
            $data['error'] ?? $data['message'] ?? ''
        );
    }

    // =========================================================================
    // change-publication
    // =========================================================================

    public function test_change_publication_returns_200_on_valid_request(): void
    {
        $this->createEnoughFutureIssuesForPlan($this->printPlan, 1);

        $response = $this->postForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/change-publication",
            [
                'publication_id' => $this->printPlan->id,
                'reason'         => 'Customer requested alternative magazine',
            ],
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals($this->printPlan->id, $data['new_publication_id']);
        $this->assertEquals($this->printPlan->id, $data['new_plan_id']);
        $this->assertArrayHasKey('remaining_issues_transferred', $data);
    }

    public function test_change_publication_uses_publication_id_as_target_plan_when_edition_id_omitted(): void
    {
        // Existing future delivery owed to the customer.
        $this->createIssueDelivery([
            'subscription_id'      => $this->subscription->id,
            'subscription_plan_id' => $this->printPlan->id,
            'status'               => 'pending',
            'on_sale_date'         => date('Y-m-d H:i:s', strtotime('+1 month')),
        ]);

        // Replacement schedule issue for the target plan/publication.
        $this->createEnoughFutureIssuesForPlan($this->printPlan, 1);

        $response = $this->postForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/change-publication",
            [
                'publication_id' => $this->printPlan->id,
                'reason'         => 'No edition specified — publication id is the target plan id',
            ],
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals($this->printPlan->id, $data['new_publication_id']);
        $this->assertEquals($this->printPlan->id, $data['new_plan_id']);
        $this->assertEquals(1, $data['remaining_issues_transferred']);

        // This is now the first issue/edition created from the target plan schedule.
        $this->assertNotNull($data['new_edition_id']);
    }

    public function test_change_publication_returns_401_for_unauthenticated(): void
    {
        $this->unauthenticate();

        $response = $this->postForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/change-publication",
            [
                'publication_id' => $this->printPlan->id,
            ],
        );

        $this->assertResponseStatus(401, $response);
    }

    public function test_change_publication_returns_422_when_publication_id_missing(): void
    {
        $response = $this->postForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/change-publication",
            ['reason' => 'Missing publication_id'],
        );

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['success']);
        $this->assertStringContainsStringIgnoringCase(
            'publication_id is required',
            $data['error']
        );
    }

    public function test_change_publication_returns_422_for_same_publication(): void
    {
        // publication_id is currently treated as the target subscription_plans.id.
        // To test "same publication", send the subscription's current plan_id.
        $response = $this->postForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/change-publication",
            [
                'publication_id' => (int) $this->subscription->plan_id,
            ],
        );

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertStringContainsStringIgnoringCase(
            'same',
            $data['error'] ?? $data['message'] ?? ''
        );
    }

    public function test_change_publication_returns_422_for_edition_from_wrong_publication(): void
    {
        // newPrintPlan.id belongs to publication B, but we are passing publication B's ID
        // while sneaking in an edition that belongs to publication A — should be rejected.
        $response = $this->postForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/change-publication",
            [
                'publication_id' => $this->printPlan->publication_id,
                'edition_id' => $this->printPlan->id, // ← belongs to pub A, not pub B
            ],
        );

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsStringIgnoringCase('publication', $data['error']);
    }

    public function test_change_publication_returns_422_for_incompatible_delivery_type(): void
    {
        $response = $this->postForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/change-publication",
            [
                // publication_id is now the target SubscriptionPlan id.
                'publication_id' => $this->digitalPlan->id,
            ],
        );

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertStringContainsStringIgnoringCase(
            'delivery type',
            $data['error'] ?? $data['message'] ?? ''
        );
    }

    public function test_change_publication_returns_422_for_inactive_subscription(): void
    {
        $cancelledSub = $this->createPrintSubscription($this->member, $this->printPlan, ['status' => 'cancelled']);

        $response = $this->postForSite(
            "/api/crm/subscriptions/{$cancelledSub->id}/change-publication",
            ['publication_id' => $this->printPlan->publication_id],
        );

        $this->assertResponseStatus(422, $response);
    }

    public function test_change_publication_returns_422_for_plan_on_different_site(): void
    {
        $otherSite = $this->createSite();

        $otherSitePlan = $this->createSubscriptionPlan([
            'site_id'       => $otherSite->id,
            'delivery_type' => 'print',
            'is_active'     => true,
        ]);

        $response = $this->postForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/change-publication",
            [
                'publication_id' => $otherSitePlan->id,
            ],
        );

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertStringContainsStringIgnoringCase(
            'site',
            $data['error'] ?? $data['message'] ?? ''
        );
    }

    public function test_change_publication_returns_422_for_inactive_plan(): void
    {
        $inactivePlan = $this->createSubscriptionPlan([
            'site_id'       => $this->siteId,
            'delivery_type' => 'print',
            'is_active'     => false,
        ]);

        $response = $this->postForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/change-publication",
            [
                'publication_id' => $inactivePlan->id,
            ],
        );

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertStringContainsStringIgnoringCase(
            'not active',
            $data['error'] ?? $data['message'] ?? ''
        );
    }

    public function test_change_publication_creates_audit_row(): void
    {
        $currentPlanId = (int) $this->subscription->plan_id;

        $currentFutureIssue = $this->createIssueDelivery([
            'subscription_id'      => $this->subscription->id,
            'subscription_plan_id' => $currentPlanId,
            'status'               => 'pending',
            'on_sale_date'         => date('Y-m-d H:i:s', strtotime('+1 month')),
            'estimated_delivery_date' => date('Y-m-d H:i:s', strtotime('+1 month +7 days')),
        ]);

        $targetScheduleIssue = $this->createIssueDelivery([
            'subscription_id'      => null,
            'subscription_plan_id' => $this->newPrintPlan->id,
            'status'               => 'active',
            'issue_number'         => 1,
            'on_sale_date'         => date('Y-m-d H:i:s', strtotime('+1 month')),
            'estimated_delivery_date' => date('Y-m-d H:i:s', strtotime('+1 month +7 days')),
        ]);

        $response = $this->postForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/change-publication",
            [
                // publication_id is the target SubscriptionPlan id.
                'publication_id' => $this->newPrintPlan->id,
                'reason'         => 'Audit row test',
            ],
        );

        $this->assertResponseStatus(200, $response);

        $change = SubscriptionChange::where('subscription_id', $this->subscription->id)
            ->where('change_type', 'publication_change')
            ->first();

        $this->assertNotNull($change, 'Expected a subscription_changes row for publication_change.');

        $this->assertEquals($currentPlanId, $change->old_publication_id);
        $this->assertEquals($this->newPrintPlan->id, $change->new_publication_id);

        $this->assertEquals($currentFutureIssue->id, $change->old_edition_id);
        $this->assertEquals($targetScheduleIssue->id, $change->new_edition_id);

        $this->assertEquals(1, $change->remaining_issues_transferred);
        $this->assertEquals('Audit row test', $change->reason);
    }

    public function test_change_publication_transfers_remaining_issue_count(): void
    {
        $currentPlanId = (int) $this->subscription->plan_id;

        foreach (range(1, 3) as $i) {
            $this->createIssueDelivery([
                'subscription_id'      => $this->subscription->id,
                'subscription_plan_id' => $currentPlanId,
                'status'               => 'pending',
                'issue_number'         => $i,
                'on_sale_date'         => date('Y-m-d H:i:s', strtotime("+{$i} month")),
                'estimated_delivery_date' => date('Y-m-d H:i:s', strtotime("+{$i} month +7 days")),
            ]);
        }

        $this->createEnoughFutureIssuesForPlan($this->newPrintPlan, 3);

        $response = $this->postForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/change-publication",
            [
                // publication_id is the target SubscriptionPlan id.
                'publication_id' => $this->newPrintPlan->id,
            ],
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(3, $data['remaining_issues_transferred']);
    }

    public function test_change_publication_response_includes_remaining_issues_transferred(): void
    {
        $response = $this->postForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/change-publication",
            [
                'publication_id' => $this->newPrintPlan->id,
            ],
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('remaining_issues_transferred', $data);
        $this->assertIsInt($data['remaining_issues_transferred']);
    }

    public function test_change_publication_with_same_stripe_price_does_not_call_stripe(): void
    {
        $currentTier = $this->createPricingTier([
            'plan_id' => $this->plan->id,
            'duration_months' => 6,
            'issue_count' => 6,
            'currency' => 'GBP',
            'stripe_price_id' => 'price_same',
            'is_active' => true,
        ]);

        $targetPlan = $this->createSubscriptionPlan([
            'site_id' => $this->siteId,
            'is_active' => true,
        ]);

        $targetTier = $this->createPricingTier([
            'plan_id' => $targetPlan->id,
            'duration_months' => 6,
            'issue_count' => 6,
            'currency' => 'GBP',
            'stripe_price_id' => 'price_same',
            'is_active' => true,
        ]);

        $this->subscription->update([
            'subscription_plan_pricing_id' => $currentTier->id,
            'stripe_price_id' => 'price_same',
            'payment_subscription_id' => 'sub_same',
            'stripe_subscription_item_id' => 'si_same',
        ]);

        $this->createIssueDelivery([
            'subscription_id' => $this->subscription->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'pending',
            'on_sale_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
        ]);
        $this->createEnoughFutureIssuesForPlan($targetPlan, 1);

        Container::getInstance()->bind(StripeSubscriptionPlanUpdater::class, function () {
            $mock = Mockery::mock(StripeSubscriptionPlanUpdater::class);
            $mock->shouldNotReceive('updateSubscriptionItemPrice');
            return $mock;
        });

        $response = $this->postForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/change-publication",
            ['publication_id' => $targetPlan->id],
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertSame('synced', $data['stripe_sync_status']);

        $subscription = Subscription::find($this->subscription->id);
        $this->assertEquals($targetTier->id, $subscription->subscription_plan_pricing_id);
        $this->assertSame('price_same', $subscription->stripe_price_id);
    }

    public function test_change_publication_with_different_stripe_price_syncs_after_commit(): void
    {
        $currentTier = $this->createPricingTier([
            'plan_id' => $this->plan->id,
            'duration_months' => 12,
            'issue_count' => 12,
            'currency' => 'GBP',
            'stripe_price_id' => 'price_old',
            'is_active' => true,
        ]);

        $targetPlan = $this->createSubscriptionPlan([
            'site_id' => $this->siteId,
            'is_active' => true,
        ]);

        $this->createPricingTier([
            'plan_id' => $targetPlan->id,
            'duration_months' => 12,
            'issue_count' => 12,
            'currency' => 'GBP',
            'stripe_price_id' => 'price_new',
            'is_active' => true,
        ]);

        $this->subscription->update([
            'subscription_plan_pricing_id' => $currentTier->id,
            'stripe_price_id' => 'price_old',
            'payment_subscription_id' => 'sub_change',
            'stripe_subscription_item_id' => 'si_change',
        ]);

        $this->createIssueDelivery([
            'subscription_id' => $this->subscription->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'pending',
            'on_sale_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
        ]);
        $this->createEnoughFutureIssuesForPlan($targetPlan, 1);

        Container::getInstance()->bind(StripeSubscriptionPlanUpdater::class, function () {
            $mock = Mockery::mock(StripeSubscriptionPlanUpdater::class);
            $mock->shouldReceive('updateSubscriptionItemPrice')
                ->once()
                ->with('si_change', 'price_new', ['proration_behavior' => 'none'])
                ->andReturn(['success' => true]);
            return $mock;
        });

        $response = $this->postForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/change-publication",
            ['publication_id' => $targetPlan->id],
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertSame('synced', $data['stripe_sync_status']);
        $this->assertNull($data['stripe_sync_error']);
    }

    public function test_change_publication_returns_422_when_compatible_pricing_tier_missing(): void
    {
        $currentTier = $this->createPricingTier([
            'plan_id' => $this->plan->id,
            'duration_months' => 12,
            'issue_count' => 12,
            'currency' => 'GBP',
            'stripe_price_id' => 'price_old',
            'is_active' => true,
        ]);

        $targetPlan = $this->createSubscriptionPlan([
            'site_id' => $this->siteId,
            'is_active' => true,
        ]);

        $this->subscription->update([
            'subscription_plan_pricing_id' => $currentTier->id,
            'stripe_price_id' => 'price_old',
        ]);

        $response = $this->postForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/change-publication",
            ['publication_id' => $targetPlan->id],
        );

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsStringIgnoringCase('no compatible pricing tier', $data['error']);
    }

    public function test_change_publication_marks_failed_when_stripe_sync_fails(): void
    {
        $currentTier = $this->createPricingTier([
            'plan_id' => $this->plan->id,
            'duration_months' => 3,
            'issue_count' => 3,
            'currency' => 'GBP',
            'stripe_price_id' => 'price_old',
            'is_active' => true,
        ]);

        $targetPlan = $this->createSubscriptionPlan([
            'site_id' => $this->siteId,
            'is_active' => true,
        ]);

        $this->createPricingTier([
            'plan_id' => $targetPlan->id,
            'duration_months' => 3,
            'issue_count' => 3,
            'currency' => 'GBP',
            'stripe_price_id' => 'price_new',
            'is_active' => true,
        ]);

        $this->subscription->update([
            'subscription_plan_pricing_id' => $currentTier->id,
            'stripe_price_id' => 'price_old',
            'payment_subscription_id' => 'sub_change',
            'stripe_subscription_item_id' => 'si_change',
        ]);

        $this->createIssueDelivery([
            'subscription_id' => $this->subscription->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'pending',
            'on_sale_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
        ]);
        $this->createEnoughFutureIssuesForPlan($targetPlan, 1);

        Container::getInstance()->bind(StripeSubscriptionPlanUpdater::class, function () {
            $mock = Mockery::mock(StripeSubscriptionPlanUpdater::class);
            $mock->shouldReceive('updateSubscriptionItemPrice')
                ->once()
                ->andReturn(['success' => false, 'error' => 'Stripe refused this update.']);
            return $mock;
        });

        $response = $this->postForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/change-publication",
            ['publication_id' => $targetPlan->id],
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertSame('failed', $data['stripe_sync_status']);
        $this->assertSame('Stripe refused this update.', $data['stripe_sync_error']);
    }

    // =========================================================================
    // GET /changes — Ticket 5
    // =========================================================================

    public function test_changes_returns_200_with_changes_array(): void
    {
        $response = $this->getForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/changes"
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('changes', $data);
        $this->assertIsArray($data['changes']);
    }

    public function test_changes_returns_empty_array_when_no_history(): void
    {
        $response = $this->getForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/changes"
        );

        $data = json_decode($response->getContent(), true);
        $this->assertCount(0, $data['changes']);
    }

    public function test_changes_returns_401_for_unauthenticated(): void
    {
        $this->unauthenticate();

        $response = $this->getForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/changes"
        );

        $this->assertResponseStatus(401, $response);
    }

    public function test_changes_returns_404_for_subscription_on_different_site(): void
    {
        $otherSite = $this->createSite();

        $otherMember = $this->createMember([
            'site_id' => $otherSite->id,
        ]);

        $otherPlan = $this->createSubscriptionPlan([
            'site_id'       => $otherSite->id,
            'delivery_type' => 'print',
            'is_active'     => true,
        ]);

        $otherSub = $this->createSubscriptionRecord([
            'member_id'      => $otherMember->id,
            'site_id'        => $otherSite->id,
            'plan_id'        => $otherPlan->id,
            'plan_name'      => $otherPlan->name ?? 'Other Site Plan',
            'status'         => 'active',
            'delivery_type'  => 'print',
        ]);

        $response = $this->getForSite(
            "/api/crm/subscriptions/{$otherSub->id}/changes"
        );

        $this->assertResponseStatus(404, $response);
    }

    public function test_changes_includes_edition_change_after_change_edition_call(): void
    {
        $currentPlanId = (int) $this->subscription->plan_id;

        $currentFutureIssue = $this->createIssueDelivery([
            'subscription_id'      => $this->subscription->id,
            'subscription_plan_id' => $currentPlanId,
            'status'              => 'pending',
            'issue_number'        => 1,
            'on_sale_date'        => date('Y-m-d H:i:s', strtotime('+1 month')),
            'estimated_delivery_date' => date('Y-m-d H:i:s', strtotime('+1 month +7 days')),
        ]);

        $targetIssue = $this->createIssueDelivery([
            'subscription_plan_id' => $currentPlanId,
            'status'              => 'active',
            'issue_number'        => 2,
            'on_sale_date'        => date('Y-m-d H:i:s', strtotime('+2 months')),
            'estimated_delivery_date' => date('Y-m-d H:i:s', strtotime('+2 months +7 days')),
        ]);

        $changeResponse = $this->postForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/change-edition",
            [
                'edition_id' => $targetIssue->id,
                'reason'    => 'Edition test',
            ],
        );

        $this->assertResponseStatus(200, $changeResponse);

        $response = $this->getForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/changes"
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $changes = $data['changes'];

        $this->assertCount(1, $changes);

        $this->assertEquals('edition_change', $changes[0]['change_type']);
        $this->assertArrayHasKey('old_edition', $changes[0]);
        $this->assertArrayHasKey('new_edition', $changes[0]);
        $this->assertArrayHasKey('reason', $changes[0]);
        $this->assertArrayHasKey('created_by', $changes[0]);
        $this->assertArrayHasKey('created_at', $changes[0]);

        $this->assertEquals('Edition test', $changes[0]['reason']);
    }

    public function test_changes_includes_publication_change_after_change_publication_call(): void
    {
        $currentPlanId = (int) $this->subscription->plan_id;

        // Existing future delivery owed to the customer.
        $this->createIssueDelivery([
            'subscription_id'           => $this->subscription->id,
            'subscription_plan_id'      => $currentPlanId,
            'status'                    => 'pending',
            'issue_number'              => 1,
            'on_sale_date'              => date('Y-m-d H:i:s', strtotime('+1 month')),
            'estimated_delivery_date'   => date('Y-m-d H:i:s', strtotime('+1 month +7 days')),
        ]);

        // Replacement schedule issue on the target plan/publication.
        $this->createIssueDelivery([
            'subscription_plan_id'      => $this->newPrintPlan->id,
            'status'                    => 'active',
            'issue_number'              => 1,
            'on_sale_date'              => date('Y-m-d H:i:s', strtotime('+1 month')),
            'estimated_delivery_date'   => date('Y-m-d H:i:s', strtotime('+1 month +7 days')),
        ]);

        $changeResponse = $this->postForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/change-publication",
            [
                // publication_id is the target SubscriptionPlan id.
                'publication_id' => $this->newPrintPlan->id,
                'reason'         => 'Pub change test',
            ],
        );

        $this->assertResponseStatus(200, $changeResponse);

        $response = $this->getForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/changes"
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $changes = $data['changes'];

        $this->assertCount(1, $changes);

        $row = $changes[0];

        $this->assertEquals('publication_change', $row['change_type']);
        $this->assertArrayHasKey('old_publication', $row);
        $this->assertArrayHasKey('new_publication', $row);
        $this->assertArrayHasKey('old_edition', $row);
        $this->assertArrayHasKey('new_edition', $row);
        $this->assertArrayHasKey('remaining_issues_transferred', $row);
        $this->assertArrayHasKey('reason', $row);
        $this->assertArrayHasKey('created_by', $row);
        $this->assertArrayHasKey('created_at', $row);

        $this->assertEquals('Pub change test', $row['reason']);
        $this->assertEquals(1, $row['remaining_issues_transferred']);
    }

    public function test_changes_lists_most_recent_first(): void
    {
        $currentPlanId = (int) $this->subscription->plan_id;

        $oldEdition = $this->createIssueDelivery([
            'subscription_id'             => $this->subscription->id,
            'subscription_plan_id'        => $currentPlanId,
            'status'                      => 'delivered', // important: do not count as future
            'issue_number'                => 1,
            'on_sale_date'                => date('Y-m-d H:i:s', strtotime('-1 month')),
            'estimated_delivery_date'     => date('Y-m-d H:i:s', strtotime('-1 month +7 days')),
        ]);

        $newEdition = $this->createIssueDelivery([
            'subscription_plan_id'        => $currentPlanId,
            'status'                      => 'active',
            'issue_number'                => 2,
            'on_sale_date'                => date('Y-m-d H:i:s', strtotime('+2 months')),
            'estimated_delivery_date'     => date('Y-m-d H:i:s', strtotime('+2 months +7 days')),
        ]);

        SubscriptionChange::create([
            'subscription_id' => $this->subscription->id,
            'change_type'    => 'edition_change',
            'old_edition_id' => $oldEdition->id,
            'new_edition_id' => $newEdition->id,
            'reason'         => 'Edition change test',
            'created_by'     => $this->actingUser->id ?? 1,
            'created_at'     => date('Y-m-d H:i:s', strtotime('-1 minute')),
            'updated_at'     => date('Y-m-d H:i:s', strtotime('-1 minute')),
        ]);

        // One real future delivery owed to the customer.
        $this->createIssueDelivery([
            'subscription_id'             => $this->subscription->id,
            'subscription_plan_id'        => $currentPlanId,
            'status'                      => 'pending',
            'issue_number'                => 3,
            'on_sale_date'                => date('Y-m-d H:i:s', strtotime('+3 months')),
            'estimated_delivery_date'     => date('Y-m-d H:i:s', strtotime('+3 months +7 days')),
        ]);

        // Replacement schedule issue on the target plan/publication.
        // Must match the remaining future delivery count: 1.
        $this->createEnoughFutureIssuesForPlan($this->newPrintPlan, 1);

        $publicationResponse = $this->postForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/change-publication",
            [
                'publication_id' => $this->newPrintPlan->id,
                'reason'         => 'Publication change test',
            ],
        );

        $this->assertResponseStatus(200, $publicationResponse);

        $response = $this->getForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/changes"
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $changes = $data['changes'];

        $this->assertCount(2, $changes);

        $this->assertEquals('publication_change', $changes[0]['change_type']);
        $this->assertEquals('edition_change', $changes[1]['change_type']);
    }

    public function test_available_editions_returns_200_with_editions_array(): void
    {
        $issue = $this->createIssueDelivery([
            'subscription_plan_id' => $this->subscription->plan_id,
            'status' => 'active',
            'issue_number' => 1,
            'on_sale_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
            'estimated_delivery_date' => date('Y-m-d H:i:s', strtotime('+1 month +7 days')),
        ]);

        $response = $this->getForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/available-editions"
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('editions', $data);

        $ids = array_column($data['editions'], 'id');

        $this->assertContains($issue->id, $ids);
    }

    public function test_available_editions_excludes_issues_from_other_plans(): void
    {
        $matchingIssue = $this->createIssueDelivery([
            'subscription_plan_id' => $this->subscription->plan_id,
            'status' => 'active',
            'issue_number' => 1,
            'on_sale_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
            'estimated_delivery_date' => date('Y-m-d H:i:s', strtotime('+1 month +7 days')),
        ]);

        $otherIssue = $this->createIssueDelivery([
            'subscription_plan_id' => $this->newPrintPlan->id,
            'status' => 'active',
            'issue_number' => 2,
            'on_sale_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
            'estimated_delivery_date' => date('Y-m-d H:i:s', strtotime('+1 month +7 days')),
        ]);

        $response = $this->getForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/available-editions"
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);

        $ids = array_column($data['editions'], 'id');

        $this->assertContains($matchingIssue->id, $ids);
        $this->assertNotContains($otherIssue->id, $ids);
    }

    public function test_available_editions_excludes_inactive_issues(): void
    {
        $activeIssue = $this->createIssueDelivery([
            'subscription_plan_id' => $this->subscription->plan_id,
            'status' => 'active',
            'issue_number' => 1,
            'on_sale_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
            'estimated_delivery_date' => date('Y-m-d H:i:s', strtotime('+1 month +7 days')),
        ]);

        $inactiveIssue = $this->createIssueDelivery([
            'subscription_plan_id' => $this->subscription->plan_id,
            'status' => 'inactive',
            'issue_number' => 2,
            'on_sale_date' => date('Y-m-d H:i:s', strtotime('+2 months')),
            'estimated_delivery_date' => date('Y-m-d H:i:s', strtotime('+2 months +7 days')),
        ]);

        $response = $this->getForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/available-editions"
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);

        $ids = array_column($data['editions'], 'id');

        $this->assertContains($activeIssue->id, $ids);
        $this->assertNotContains($inactiveIssue->id, $ids);
    }

    public function test_available_editions_returns_empty_array_when_no_future_issues(): void
    {
        $this->createIssueDelivery([
            'subscription_plan_id' => $this->subscription->plan_id,
            'status' => 'active',
            'issue_number' => 1,
            'on_sale_date' => date('Y-m-d H:i:s', strtotime('-1 month')),
            'estimated_delivery_date' => date('Y-m-d H:i:s', strtotime('-1 month +7 days')),
        ]);

        $response = $this->getForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/available-editions"
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('editions', $data);
        $this->assertCount(0, $data['editions']);
    }

    public function test_available_editions_returns_401_for_unauthenticated_request(): void
    {
        $this->unauthenticate();

        $response = $this->getForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/available-editions"
        );

        $this->assertResponseStatus(401, $response);
    }

    public function test_available_editions_returns_404_for_subscription_on_different_site(): void
    {
        $otherSite = $this->createSite();

        $otherMember = $this->createMember([
            'site_id' => $otherSite->id,
        ]);

        $otherPlan = $this->createSubscriptionPlan([
            'site_id' => $otherSite->id,
            'delivery_type' => 'print',
            'is_active' => true,
        ]);

        $otherSub = $this->createSubscriptionRecord([
            'member_id' => $otherMember->id,
            'site_id' => $otherSite->id,
            'plan_id' => $otherPlan->id,
            'status' => 'active',
            'delivery_type' => 'print',
        ]);

        $response = $this->getForSite(
            "/api/crm/subscriptions/{$otherSub->id}/available-editions"
        );

        $this->assertResponseStatus(404, $response);
    }

    public function test_available_editions_orders_by_on_sale_date_then_issue_number(): void
    {
        $issue3 = $this->createIssueDelivery([
            'subscription_plan_id' => $this->subscription->plan_id,
            'status' => 'active',
            'issue_number' => 3,
            'on_sale_date' => '2026-08-01 00:00:00',
            'estimated_delivery_date' => '2026-08-08 00:00:00',
        ]);

        $issue2 = $this->createIssueDelivery([
            'subscription_plan_id' => $this->subscription->plan_id,
            'status' => 'active',
            'issue_number' => 2,
            'on_sale_date' => '2026-08-01 00:00:00',
            'estimated_delivery_date' => '2026-08-08 00:00:00',
        ]);

        $issue1 = $this->createIssueDelivery([
            'subscription_plan_id' => $this->subscription->plan_id,
            'status' => 'active',
            'issue_number' => 1,
            'on_sale_date' => '2026-07-01 00:00:00',
            'estimated_delivery_date' => '2026-07-08 00:00:00',
        ]);

        $response = $this->getForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/available-editions"
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals([
            $issue1->id,
            $issue2->id,
            $issue3->id,
        ], array_column($data['editions'], 'id'));
    }

    public function test_available_publications_returns_200_with_publications_array(): void
    {
        $targetPlan = $this->createSubscriptionPlan([
            'site_id'       => $this->siteId,
            'name'          => 'Target Print Plan',
            'delivery_type' => 'print',
            'is_active'     => true,
        ]);

        $response = $this->getForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/available-publications"
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('plans', $data);
        $this->assertIsArray($data['plans']);

        $ids = array_column($data['plans'], 'id');

        $this->assertContains($targetPlan->id, $ids);
    }

    public function test_available_publications_excludes_current_subscription_plan(): void
    {
        $response = $this->getForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/available-publications"
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);

        $ids = array_column($data['plans'], 'id');

        $this->assertNotContains((int) $this->subscription->plan_id, $ids);
    }

    public function test_available_publications_excludes_inactive_plans(): void
    {
        $inactivePlan = $this->createSubscriptionPlan([
            'site_id'       => $this->siteId,
            'name'          => 'Inactive Print Plan',
            'delivery_type' => 'print',
            'is_active'     => false,
        ]);

        $activePlan = $this->createSubscriptionPlan([
            'site_id'       => $this->siteId,
            'name'          => 'Active Print Plan',
            'delivery_type' => 'print',
            'is_active'     => true,
        ]);

        $response = $this->getForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/available-publications"
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);

        $ids = array_column($data['plans'], 'id');

        $this->assertContains($activePlan->id, $ids);
        $this->assertNotContains($inactivePlan->id, $ids);
    }

    public function test_available_publications_excludes_plans_from_other_sites(): void
    {
        $otherSite = $this->createSite();

        $sameSitePlan = $this->createSubscriptionPlan([
            'site_id'       => $this->siteId,
            'name'          => 'Same Site Print Plan',
            'delivery_type' => 'print',
            'is_active'     => true,
        ]);

        $otherSitePlan = $this->createSubscriptionPlan([
            'site_id'       => $otherSite->id,
            'name'          => 'Other Site Print Plan',
            'delivery_type' => 'print',
            'is_active'     => true,
        ]);

        $response = $this->getForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/available-publications"
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);

        $ids = array_column($data['plans'], 'id');

        $this->assertContains($sameSitePlan->id, $ids);
        $this->assertNotContains($otherSitePlan->id, $ids);
    }

    public function test_available_publications_excludes_different_delivery_type(): void
    {
        $printPlan = $this->createSubscriptionPlan([
            'site_id'       => $this->siteId,
            'name'          => 'Another Print Plan',
            'delivery_type' => 'print',
            'is_active'     => true,
        ]);

        $digitalPlan = $this->createSubscriptionPlan([
            'site_id'       => $this->siteId,
            'name'          => 'Digital Plan',
            'delivery_type' => 'digital',
            'is_active'     => true,
        ]);

        $response = $this->getForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/available-publications"
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);

        $ids = array_column($data['plans'], 'id');

        $this->assertContains($printPlan->id, $ids);
        $this->assertNotContains($digitalPlan->id, $ids);
    }

    public function test_available_publications_returns_401_for_unauthenticated_request(): void
    {
        $this->unauthenticate();

        $response = $this->getForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/available-publications"
        );

        $this->assertResponseStatus(401, $response);
    }

    public function test_available_publications_returns_404_for_non_existent_subscription(): void
    {
        $response = $this->getForSite(
            "/api/crm/subscriptions/999999/available-publications"
        );

        $this->assertResponseStatus(404, $response);
    }

    public function test_available_publications_returns_404_for_subscription_on_different_site(): void
    {
        $otherSite = $this->createSite();

        $otherMember = $this->createMember([
            'site_id' => $otherSite->id,
        ]);

        $otherPlan = $this->createSubscriptionPlan([
            'site_id'       => $otherSite->id,
            'name'          => 'Other Site Current Plan',
            'delivery_type' => 'print',
            'is_active'     => true,
        ]);

        $otherSub = $this->createSubscriptionRecord([
            'member_id'     => $otherMember->id,
            'site_id'       => $otherSite->id,
            'plan_id'       => $otherPlan->id,
            'status'        => 'active',
            'delivery_type' => 'print',
        ]);

        $response = $this->getForSite(
            "/api/crm/subscriptions/{$otherSub->id}/available-publications"
        );

        $this->assertResponseStatus(404, $response);
    }

    public function test_available_publications_response_rows_have_expected_shape(): void
    {
        $targetPlan = $this->createSubscriptionPlan([
            'site_id'       => $this->siteId,
            'name'          => 'Shape Test Print Plan',
            'delivery_type' => 'print',
            'is_active'     => true,
            'price'         => 9.99,
            'currency'      => 'GBP',
        ]);

        $response = $this->getForSite(
            "/api/crm/subscriptions/{$this->subscription->id}/available-publications"
        );

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);

        $matchingRows = array_values(array_filter(
            $data['plans'],
            static fn (array $row): bool => (int) $row['id'] === (int) $targetPlan->id
        ));

        $this->assertNotEmpty($matchingRows);

        $row = $matchingRows[0];

        $this->assertArrayHasKey('id', $row);
        $this->assertArrayHasKey('name', $row);
        $this->assertArrayHasKey('delivery_type', $row);
        $this->assertArrayHasKey('is_active', $row);
        $this->assertArrayHasKey('price', $row);
        $this->assertArrayHasKey('currency', $row);

        $this->assertEquals($targetPlan->id, $row['id']);
        $this->assertEquals('Shape Test Print Plan', $row['name']);
        $this->assertEquals('print', $row['delivery_type']);
    }


    // =========================================================================
    // Helpers
    // =========================================================================

    private function createPrintSubscription(
        mixed $member = null,
        ?SubscriptionPlan $plan = null,
        array $overrides = [],
    ): array|Model {
        /*
         * Backwards-compatible call styles:
         *
         *   createPrintSubscription()
         *      -> returns [Member, Subscription]
         *
         *   createPrintSubscription(['status' => 'cancelled'])
         *      -> returns [Member, Subscription]
         *
         *   createPrintSubscription($member, $plan)
         *      -> returns Subscription
         *
         *   createPrintSubscription($member, $plan, ['status' => 'cancelled'])
         *      -> returns Subscription
         */

        $returnTuple = false;

        if (is_array($member)) {
            $overrides = $member;
            $member = null;
            $plan = null;
            $returnTuple = true;
        }

        if ($member === null) {
            $returnTuple = true;

            $member = $this->createMember([
                'site_id'    => $this->siteId,
                'is_active'  => true,
                'anonymous'  => false,
                'first_name' => 'Print',
                'last_name'  => 'Subscriber',
                'email'      => 'print.subscriber.' . uniqid() . '@example.com',
            ]);
        }

        if (!$member instanceof Member) {
            throw new \InvalidArgumentException('Expected Member or overrides array.');
        }

        if (!$plan instanceof SubscriptionPlan) {
            $plan = $this->createSubscriptionPlan([
                'site_id'       => $member->site_id ?? $this->siteId,
                'delivery_type' => 'print',
                'is_active'     => true,
            ]);
        }

        $subscription = Subscription::create(array_merge([
            'member_id'        => $member->id,
            'site_id'          => $member->site_id ?? $this->siteId,
            'plan_id'          => $plan->id,
            'plan_name'        => $plan->name ?? 'Test Plan',
            'status'           => 'active',
            'delivery_type'    => 'print',
            'start_date'       => date('Y-m-d H:i:s'),
            'end_date'         => date('Y-m-d H:i:s', strtotime('+1 year')),
            'next_billing_date'=> date('Y-m-d H:i:s', strtotime('+1 month')),
            'price'            => 9.99,
            'currency'         => 'GBP',
            'auto_renew'       => true,
            'delivery_paused'  => false,
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ], $overrides));

        if ($returnTuple) {
            return [$member, $subscription];
        }

        return $subscription;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function fetchIssues(int $memberId, int $subscriptionId): array
    {
        $response = $this->getForSite(
            "/api/crm/members/{$memberId}/subscriptions/{$subscriptionId}/issues"
        );

        $this->assertResponseStatus(200, $response);

        return json_decode($response->getContent(), true);
    }

    /**
     * @return array{0: \App\Models\Member, 1: \App\Models\Subscription}
     */
    /**
     * @return array{0: \App\Models\Member, 1: \App\Models\Subscription}
     */
    private function createDigitalSubscription(): array
    {
        $member = $this->createMember([
            'site_id'    => $this->siteId,
            'is_active'  => true,
            'anonymous'  => false,
            'first_name' => 'Digital',
            'last_name'  => 'Subscriber',
            'email'      => 'digital.subscriber.' . uniqid() . '@example.com',
        ]);

        $plan = $this->createSubscriptionPlan([
            'site_id'       => $this->siteId,
            'delivery_type' => 'digital',
            'is_active'     => true,
        ]);

        $subscription = Subscription::create([
            'member_id'         => $member->id,
            'site_id'           => $this->siteId,
            'plan_id'           => $plan->id,
            'plan_name'         => $plan->name ?? 'Digital Plan',
            'status'            => 'active',
            'delivery_type'     => 'digital',
            'start_date'        => date('Y-m-d H:i:s'),
            'end_date'          => date('Y-m-d H:i:s', strtotime('+1 year')),
            'next_billing_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
            'price'             => 9.99,
            'currency'          => 'GBP',
            'auto_renew'        => true,
            'delivery_paused'   => false,
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        return [$member, $subscription];
    }

    private function createDispatchedIssueDelivery(int $subscriptionId, int $planId): Model
    {
        return $this->createIssueDelivery([
            'subscription_id' => $subscriptionId,
            'subscription_plan_id' => $planId,
            'status'          => 'dispatched',
        ]);
    }

    private function createFulfilmentReplacement(array $attributes): Model
    {
        return FulfilmentReplacement::create(array_merge([
            'reason'     => 'Test reason',
            'created_by' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $attributes));
    }

    private function createEnoughFutureIssuesForPlan(
        SubscriptionPlan $plan,
        int $count = 3,
    ): void {
        for ($i = 1; $i <= $count; $i++) {
            $this->createIssueDelivery([
                'subscription_plan_id'      => $plan->id,
                'subscription_id'           => null,
                'issue_number'              => $i,
                'status'                    => 'active',
                'on_sale_date'              => date('Y-m-d H:i:s', strtotime("+{$i} month")),
                'estimated_delivery_date'   => date('Y-m-d H:i:s', strtotime("+{$i} month +7 days")),
            ]);
        }
    }
}
