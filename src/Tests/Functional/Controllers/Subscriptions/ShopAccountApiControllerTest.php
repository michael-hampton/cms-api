<?php

namespace App\Tests\Functional\Controllers\Subscriptions;

use App\Enums\Orders\OrderCancellationReason;
use App\Enums\Subscriptions\SubscriptionCancellationReason;
use App\Models\Order;
use App\Models\Subscription;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

/**
 * Functional tests for ShopAccountApiController.
 *
 * These tests exercise the HTTP layer end-to-end against a real database.
 * Service behaviour (Stripe calls, refund logic) is verified separately
 * in unit/service tests — here we only assert on HTTP contract and ownership
 * guards.
 */
class ShopAccountApiControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    // =========================================================================
    // POST /account/subscriptions/{id}/cancel
    // =========================================================================

    public function test_cancel_subscription_succeeds_with_valid_reason(): void
    {
        $member = $this->createMember();
        $subscription = $this->createSubscription(['member_id' => $member->id, 'status' => 'active']);
        $this->actingAsMember($member);

        $response = $this->post(
            "/press-stack/account/subscriptions/{$subscription->id}/cancel",
            ['reason' => SubscriptionCancellationReason::TooExpensive->value]
        );

        $this->assertResponseStatus(200, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }

    public function test_cancel_subscription_returns_422_without_reason(): void
    {
        $member = $this->createMember();
        $subscription = $this->createSubscription(['member_id' => $member->id, 'status' => 'active']);
        $this->actingAsMember($member);

        $response = $this->post(
            "/press-stack/account/subscriptions/{$subscription->id}/cancel",
            ['reason' => '']
        );

        $this->assertResponseStatus(422, $response);
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['data']['success']);
        $this->assertEquals('Please select a cancellation reason.', $data['data']['message']);
    }

    public function test_cancel_subscription_returns_422_with_invalid_reason(): void
    {
        $member = $this->createMember();
        $subscription = $this->createSubscription(['member_id' => $member->id, 'status' => 'active']);

        $this->actingAsMember($member);

        $response = $this->post(
            "/press-stack/account/subscriptions/{$subscription->id}/cancel",
            ['reason' => 'not_a_real_reason']
        );

        $this->assertResponseStatus(422, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['data']['success']);
    }

    public function test_cancel_subscription_returns_404_for_another_members_subscription(): void
    {
        $member = $this->createMember();
        $otherMember = $this->createMember();
        $subscription = $this->createSubscription(['member_id' => $otherMember->id, 'status' => 'active']);

        $this->actingAsMember($member);

        $response = $this->post(
            "/press-stack/account/subscriptions/{$subscription->id}/cancel",
            ['reason' => SubscriptionCancellationReason::TooExpensive->value]
        );

        $this->assertResponseStatus(404, $response);
    }

    public function test_cancel_subscription_returns_404_for_nonexistent_subscription(): void
    {
        $member = $this->createMember();
        $this->actingAsMember($member);

        $response = $this->post(
            '/press-stack/account/subscriptions/99999/cancel',
            ['reason' => SubscriptionCancellationReason::TooExpensive->value]
        );

        $this->assertResponseStatus(404, $response);
    }

    public function test_cancel_subscription_accepts_other_reason_with_notes(): void
    {
        $member = $this->createMember();
        $subscription = $this->createSubscription(['member_id' => $member->id, 'status' => 'active']);

        $this->actingAsMember($member);

        $response = $this->post(
            "/press-stack/account/subscriptions/{$subscription->id}/cancel",
            [
                'reason' => SubscriptionCancellationReason::Other->value,
                'other_text' => 'Moving abroad and cannot use the service.',
            ]
        );

        $this->assertResponseStatus(200, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }

    // =========================================================================
    // POST /account/subscriptions/{id}/pause
    // =========================================================================

    public function test_pause_subscription_succeeds_for_eligible_subscription(): void
    {
        $member = $this->createMember();
        $subscription = $this->createSubscription(['member_id' => $member->id, 'status' => 'active']);
        $this->actingAsMember($member);

        $response = $this->post(
            "/press-stack/account/subscriptions/{$subscription->id}/pause",
            ['pause_until' => date('Y-m-d', strtotime('+30 days'))]
        );

        $this->assertResponseStatus(200, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['data']['success']);
        $this->assertArrayHasKey('status', $data['data']);
        $this->assertArrayHasKey('pause_until', $data['data']);
    }

    public function test_pause_subscription_returns_422_when_not_eligible(): void
    {
        $member = $this->createMember();
        $this->actingAsMember($member);
        // A cancelled subscription cannot be paused
        $subscription = $this->createSubscription(['member_id' => $member->id, 'status' => 'cancelled']);

        $response = $this->post(
            "/press-stack/account/subscriptions/{$subscription->id}/pause",
            []
        );

        $this->assertResponseStatus(422, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['data']['success']);
    }

    // =========================================================================
    // POST /account/subscriptions/{id}/resume
    // =========================================================================

    public function test_resume_subscription_succeeds_for_paused_subscription(): void
    {
        $member = $this->createMember();
        $this->actingAsMember($member);
        $subscription = $this->createSubscription(['member_id' => $member->id, 'status' => 'paused']);

        $response = $this->post(
            "/press-stack/account/subscriptions/{$subscription->id}/resume",
            []
        );

        $this->assertResponseStatus(200, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['data']['success']);
        $this->assertArrayHasKey('status', $data['data']);
        $this->assertArrayHasKey('next_billing_date', $data['data']);
    }

    public function test_resume_subscription_returns_422_when_not_paused(): void
    {
        $member = $this->createMember();
        $this->actingAsMember($member);
        $subscription = $this->createSubscription(['member_id' => $member->id, 'status' => 'active']);

        $response = $this->post(
            "/press-stack/account/subscriptions/{$subscription->id}/resume",
            []
        );

        $this->assertResponseStatus(422, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['data']['success']);
    }

    // =========================================================================
    // POST /account/orders/{id}/cancel
    // =========================================================================

    public function test_cancel_order_succeeds_for_pending_order(): void
    {
        $member = $this->createMember();
        $this->actingAsMember($member);
        $order = $this->createOrder(['user_id' => $member->id, 'status' => 'pending']);

        $response = $this->post(
            "/press-stack/account/orders/{$order->id}/cancel",
            ['reason' => OrderCancellationReason::ChangedMind->value]
        );

        $this->assertResponseStatus(200, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['data']['success']);

        $refreshed = Order::find($order->id);
        $this->assertEquals('cancelled', $refreshed->status);
    }

    public function test_cancel_order_succeeds_for_processing_order(): void
    {
        $member = $this->createMember();
        $this->actingAsMember($member);
        $order = $this->createOrder(['user_id' => $member->id, 'status' => 'processing']);

        $response = $this->post(
            "/press-stack/account/orders/{$order->id}/cancel",
            ['reason' => OrderCancellationReason::WrongItem->value]
        );

        $this->assertResponseStatus(200, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['data']['success']);
    }

    public function test_cancel_order_cancels_linked_subscription_immediately(): void
    {
        $member = $this->createMember();
        $this->actingAsMember($member);

        $subscription = $this->createSubscription([
            'member_id' => $member->id,
            'status' => 'active',
            'auto_renew' => true,
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
        ]);

        $order = $this->createOrder([
            'user_id' => $member->id,
            'status' => 'pending',
            'one_time_subscription_id' => $subscription->id,
        ]);

        $response = $this->post(
            "/press-stack/account/orders/{$order->id}/cancel",
            ['reason' => OrderCancellationReason::ChangedMind->value]
        );

        $this->assertResponseStatus(200, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['data']['success']);

        $refreshedOrder = Order::find($order->id);
        $refreshedSubscription = Subscription::find($subscription->id);

        $this->assertEquals('cancelled', $refreshedOrder->status);
        $this->assertEquals('cancelled', $refreshedSubscription->status);
        $this->assertFalse((bool)$refreshedSubscription->auto_renew);
        $this->assertEquals(SubscriptionCancellationReason::Other->value, $refreshedSubscription->cancellation_reason);
        $this->assertStringContainsString('Order cancellation reason: Changed my mind', $refreshedSubscription->cancellation_notes);
    }

    public function test_cancel_order_returns_404_for_completed_order(): void
    {
        $member = $this->createMember();
        $this->actingAsMember($member);
        $order = $this->createOrder(['user_id' => $member->id, 'status' => 'completed']);

        $response = $this->post(
            "/press-stack/account/orders/{$order->id}/cancel",
            ['reason' => OrderCancellationReason::ChangedMind->value]
        );

        $this->assertResponseStatus(404, $response);
    }

    public function test_cancel_order_returns_422_without_reason(): void
    {
        $member = $this->createMember();
        $this->actingAsMember($member);
        $order = $this->createOrder(['user_id' => $member->id, 'status' => 'pending']);

        $response = $this->post(
            "/press-stack/account/orders/{$order->id}/cancel",
            ['reason' => '']
        );

        $this->assertResponseStatus(422, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['data']['success']);
        $this->assertEquals('Please select a cancellation reason.', $data['data']['message']);
    }

    public function test_cancel_order_returns_422_with_invalid_reason(): void
    {
        $member = $this->createMember();
        $this->actingAsMember($member);
        $order = $this->createOrder(['user_id' => $member->id, 'status' => 'pending']);

        $response = $this->post(
            "/press-stack/account/orders/{$order->id}/cancel",
            ['reason' => 'not_valid']
        );

        $this->assertResponseStatus(422, $response);
    }

    public function test_cancel_order_returns_404_for_another_members_order(): void
    {
        $member = $this->createMember();
        $this->actingAsMember($member);
        $otherMember = $this->createMember();
        $order = $this->createOrder(['user_id' => $otherMember->id, 'status' => 'pending']);

        $response = $this->post(
            "/press-stack/account/orders/{$order->id}/cancel",
            ['reason' => OrderCancellationReason::ChangedMind->value]
        );

        $this->assertResponseStatus(404, $response);
    }

    public function test_cancel_order_returns_404_for_nonexistent_order(): void
    {
        $member = $this->createMember();
        $this->actingAsMember($member);

        $response = $this->post(
            '/press-stack/account/orders/99999/cancel',
            ['reason' => OrderCancellationReason::ChangedMind->value]
        );

        $this->assertResponseStatus(404, $response);
    }

    // =========================================================================
    // GET /account/billing/payment-methods
    // =========================================================================

    public function test_payment_methods_returns_empty_list_when_no_stripe_customer(): void
    {
        $member = $this->createMember(['stripe_customer_id' => null]);
        $this->actingAsMember($member);

        $response = $this->get('/press-stack/account/billing/payment-methods');

        // The service should handle a null customer gracefully
        $this->assertResponseStatus(200, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['data']['success']);
        $this->assertIsArray($data['data']['payment_methods']);
    }

    // =========================================================================
    // POST /account/billing/set-default
    // =========================================================================

    public function test_set_default_card_returns_422_without_payment_method_id(): void
    {
        $member = $this->createMember();
        $this->actingAsMember($member);

        $response = $this->post('/press-stack/account/billing/set-default', []);

        $this->assertResponseStatus(422, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['data']['success']);
        $this->assertEquals('Payment method ID required.', $data['data']['message']);
    }

    public function test_set_default_card_returns_404_for_unowned_payment_method(): void
    {
        $member = $this->createMember(['stripe_customer_id' => 'cus_test123']);
        $this->actingAsMember($member);

        $response = $this->post(
            '/press-stack/account/billing/set-default',
            ['payment_method_id' => 'pm_belongs_to_someone_else']
        );

        $this->assertResponseStatus(404, $response);
    }

    // =========================================================================
    // POST /account/billing/remove-card
    // =========================================================================

    public function test_remove_card_returns_422_without_payment_method_id(): void
    {
        $member = $this->createMember();
        $this->actingAsMember($member);

        $response = $this->post('/press-stack/account/billing/remove-card', []);

        $this->assertResponseStatus(422, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['data']['success']);
        $this->assertEquals('Payment method ID required.', $data['data']['message']);
    }

    public function test_remove_card_returns_404_for_unowned_payment_method(): void
    {
        $member = $this->createMember(['stripe_customer_id' => 'cus_test123']);
        $this->actingAsMember($member);

        $response = $this->post(
            '/press-stack/account/billing/remove-card',
            ['payment_method_id' => 'pm_belongs_to_someone_else']
        );

        $this->assertResponseStatus(404, $response);
    }
}
