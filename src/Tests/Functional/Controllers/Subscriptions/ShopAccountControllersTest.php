<?php

namespace App\Tests\Functional\Controllers\Subscriptions;

use App\Enums\Subscriptions\SubscriptionCancellationReason;
use App\Enums\Subscriptions\SubscriptionType;
use App\Framework\Http\Response;
use App\Models\Address;
use App\Models\Member;
use App\Models\MemberSubscriptionPreference;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ShopAccountControllersTest extends FunctionalTestCase
{
    use CreatesTestData;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = $this->createMember([
            'email' => 'shop-account-member@example.com',
            'first_name' => 'Shop',
            'last_name' => 'Member',
            'display_name' => 'Shop Member',
            'is_active' => true,
            'status' => 'active',
        ]);

        $this->actingAsMember($this->member);
    }

    public function testOverviewPageLoadsForAuthenticatedMemberUsingDatabaseSubscriptionsAndOrders(): void
    {
        $subscription = $this->memberSubscription();
        $order = $this->memberOrder();

        $response = $this->getAccount('/press-stack/account');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString((string) $subscription->plan_name, $response->getContent());
        $this->assertStringContainsString((string) $order->order_number, $response->getContent());
    }

    public function testSubscriptionsPageLoadsMemberSubscriptionsFromDatabase(): void
    {
        $subscription = $this->memberSubscription([
            'plan_name' => 'Database Functional Plan',
        ]);

        $response = $this->getAccount('/press-stack/account/subscriptions');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString((string) $subscription->plan_name, $response->getContent());
    }

    public function testOrdersPageLoadsMemberOrdersFromDatabase(): void
    {
        $order = $this->memberOrder([
            'order_number' => 'SHOP-ACCOUNT-ORDER-1',
        ]);

        $response = $this->getAccount('/press-stack/account/orders');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString((string) $order->order_number, $response->getContent());
    }

    public function testOrderDetailRedirectsWhenOrderDoesNotBelongToMember(): void
    {
        $otherMember = $this->createMember([
            'email' => 'other-shop-member@example.com',
            'is_active' => true,
        ]);
        $otherOrder = $this->createOrder([
            'user_id' => $otherMember->id,
            'order_number' => 'OTHER-ORDER-1',
            'status' => 'pending',
            'total_amount' => 15.00,
        ]);

        $response = $this->getAccount("/press-stack/account/orders/{$otherOrder->id}");

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/account/orders', $response->getHeader('Location'));
    }

    public function testBillingPageLoadsForAuthenticatedMember(): void
    {
        $response = $this->getAccount('/press-stack/account/billing');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRenewRedirectsBackWhenSubscriptionIsNotRenewable(): void
    {
        $subscription = $this->memberSubscription([
            'status' => 'active',
            'end_date' => now_datetime()->modify('+1 month'),
        ]);

        $response = $this->getAccount("/press-stack/account/subscriptions/{$subscription->id}/renew");

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/press-stack/account/subscriptions', $response->getHeader('Location'));
    }

    public function testResubscribeRedirectsBackToSubscriptions(): void
    {
        $subscription = $this->memberSubscription([
            'status' => 'expired',
            'end_date' => now_datetime()->modify('-1 month'),
        ]);

        $response = $this->getAccount("/press-stack/account/subscriptions/{$subscription->id}/resubscribe");

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/press-stack/account/subscriptions', $response->getHeader('Location'));
    }

    public function testCancelSubscriptionRequiresValidCancellationReasonBeforeTouchingDatabase(): void
    {
        $subscription = $this->memberSubscription();

        $response = $this->postAccount("/press-stack/account/subscriptions/{$subscription->id}/cancel", [
            'reason' => 'not-a-valid-reason',
        ]);

        $subscription = Subscription::find($subscription->id);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertFalse($this->responseJson($response)['success']);
        $this->assertNull($subscription->cancelled_at);
        $this->assertTrue((bool) $subscription->auto_renew);
    }

    public function testCancelSubscriptionUpdatesOwnedSubscriptionInDatabase(): void
    {
        $subscription = $this->memberSubscription([
            'auto_renew' => true,
            'end_date' => now_datetime()->modify('+1 month'),
            'type' => 'free',
        ]);

        $response = $this->postAccount("/press-stack/account/subscriptions/{$subscription->id}/cancel", [
            'reason' => SubscriptionCancellationReason::TooExpensive->value,
        ]);

        $subscription = Subscription::find($subscription->id);
        $data = $this->responseJson($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertFalse((bool) $subscription->auto_renew);
        $this->assertTrue((bool) $subscription->cancel_at_period_end);
        $this->assertSame(SubscriptionCancellationReason::TooExpensive->value, $subscription->cancellation_reason);
        $this->assertNotNull($subscription->cancelled_at);
    }

    public function testReactivateSubscriptionRestoresScheduledCancellationInDatabase(): void
    {
        $subscription = $this->memberSubscription([
            'auto_renew' => false,
            'cancel_at_period_end' => true,
            'cancelled_at' => now_datetime(),
            'cancellation_reason' => SubscriptionCancellationReason::Other->value,
            'end_date' => now_datetime()->modify('+1 month'),
        ]);

        $response = $this->postAccount("/press-stack/account/subscriptions/{$subscription->id}/reactivate");

        $subscription = Subscription::find($subscription->id);
        $data = $this->responseJson($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertSame('active', $subscription->status);
        $this->assertTrue((bool) $subscription->auto_renew);
        $this->assertFalse((bool) $subscription->cancel_at_period_end);
        $this->assertNull($subscription->cancelled_at);
        $this->assertNull($subscription->cancellation_reason);
    }

    public function testPauseSubscriptionUpdatesOwnedSubscriptionInDatabase(): void
    {
        $subscription = $this->memberSubscription([
            'auto_renew' => true,
            'status' => 'active',
            'payment_subscription_id' => null,
        ]);

        $response = $this->postAccount("/press-stack/account/subscriptions/{$subscription->id}/pause", [
            'pause_until' => now_datetime()->modify('+14 days')->format('Y-m-d'),
        ]);

        $subscription = Subscription::find($subscription->id);
        $data = $this->responseJson($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertSame('paused', $subscription->status);
        $this->assertFalse((bool) $subscription->auto_renew);
        $this->assertTrue((bool) $subscription->auto_renew_before_pause);
        $this->assertNotNull($subscription->paused_at);
    }

    public function testResumeSubscriptionUpdatesOwnedSubscriptionInDatabase(): void
    {
        $subscription = $this->memberSubscription([
            'status' => 'paused',
            'auto_renew' => false,
            'auto_renew_before_pause' => true,
            'paused_at' => now_datetime(),
            'pause_until' => now_datetime()->modify('+14 days'),
            'payment_subscription_id' => null,
        ]);

        $response = $this->postAccount("/press-stack/account/subscriptions/{$subscription->id}/resume");

        $subscription = Subscription::find($subscription->id);
        $data = $this->responseJson($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertSame('active', $subscription->status);
        $this->assertTrue((bool) $subscription->auto_renew);
        $this->assertNull($subscription->paused_at);
        $this->assertNull($subscription->pause_until);
        $this->assertNotNull($subscription->resumed_at);
    }

    public function testBillingDatePreviewUsesDatabaseOwnershipAndRejectsNonStripeSubscription(): void
    {
        $subscription = $this->memberSubscription([
            'payment_subscription_id' => null,
        ]);

        $response = $this->postAccount("/press-stack/account/subscriptions/{$subscription->id}/billing-date/preview", [
            'day_of_month' => '15',
        ]);

        $data = $this->responseJson($response);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertFalse($data['success']);
        $this->assertSame('Can only preview billing date changes for Stripe subscriptions', $data['message']);
    }

    public function testBillingDateUpdateRejectsInvalidDayBeforeCallingStripe(): void
    {
        $subscription = $this->memberSubscription([
            'payment_subscription_id' => 'sub_test_should_not_be_called',
        ]);

        $response = $this->postAccount("/press-stack/account/subscriptions/{$subscription->id}/billing-date", [
            'day_of_month' => '32',
        ]);

        $subscription = Subscription::find($subscription->id);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('Please select a day between 1 and 31.', $this->responseJson($response)['message']);
        $this->assertNull($subscription->billing_day_of_month);
    }

    public function testSubscriptionHistoryReturnsEventsFromDatabase(): void
    {
        $subscription = $this->memberSubscription();
        SubscriptionEvent::create([
            'subscription_id' => $subscription->id,
            'event_type' => 'subscription_created',
            'metadata' => ['source' => 'functional_test'],
            'occurred_at' => now_datetime(),
        ]);

        $response = $this->getAccount("/press-stack/account/subscriptions/{$subscription->id}/history?page=1&per_page=10");
        $data = $this->responseJson($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertSame(1, $data['pagination']['total']);
        $this->assertSame('subscription_created', $data['events'][0]['event_type']);
        $this->assertSame('functional_test', $data['events'][0]['metadata']['source']);
    }

    public function testDeliveryStatusReadsPauseStateFromDatabase(): void
    {
        $subscription = $this->memberSubscription([
            'delivery_type' => SubscriptionType::PRINTED->value,
            'delivery_paused' => true,
            'delivery_pause_start' => now_datetime()->modify('-1 day'),
            'delivery_pause_end' => now_datetime()->modify('+7 days'),
            'delivery_pause_reason' => 'Holiday',
        ]);

        $response = $this->getAccount("/press-stack/account/subscriptions/{$subscription->id}/delivery");
        $data = $this->responseJson($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertTrue($data['is_paused']);
        $this->assertSame('Holiday', $data['reason']);
    }

    public function testDeliveryPauseUpdatesDatabase(): void
    {
        $subscription = $this->memberSubscription([
            'delivery_type' => SubscriptionType::PRINTED->value,
        ]);

        $response = $this->postAccount("/press-stack/account/subscriptions/{$subscription->id}/delivery/pause", [
            'pause_start' => now_datetime()->modify('+3 days')->format('Y-m-d'),
            'pause_end' => now_datetime()->modify('+10 days')->format('Y-m-d'),
            'reason' => 'Away',
        ]);

        $subscription = Subscription::find($subscription->id);
        $data = $this->responseJson($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertTrue((bool) $subscription->delivery_paused);
        $this->assertSame('Away', $subscription->delivery_pause_reason);
    }

    public function testDeliveryResumeClearsPauseFieldsInDatabase(): void
    {
        $subscription = $this->memberSubscription([
            'delivery_type' => SubscriptionType::PRINTED->value,
            'delivery_paused' => true,
            'delivery_pause_start' => now_datetime()->modify('+1 day'),
            'delivery_pause_end' => now_datetime()->modify('+5 days'),
            'delivery_pause_reason' => 'Away',
        ]);

        $response = $this->postAccount("/press-stack/account/subscriptions/{$subscription->id}/delivery/resume");

        $subscription = Subscription::find($subscription->id);
        $data = $this->responseJson($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertFalse((bool) $subscription->delivery_paused);
        $this->assertNull($subscription->delivery_pause_start);
        $this->assertNull($subscription->delivery_pause_end);
        $this->assertNull($subscription->delivery_pause_reason);
    }

    public function testAutoRenewUpdatePersistsToDatabase(): void
    {
        $subscription = $this->memberSubscription([
            'auto_renew' => true,
            'consent_given' => true,
        ]);

        $response = $this->postAccount("/press-stack/account/subscriptions/{$subscription->id}/auto-renew", [
            'auto_renew' => '0',
            'consent_given' => '1',
        ]);

        $subscription = Subscription::find($subscription->id);
        $data = $this->responseJson($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertFalse((bool) $subscription->auto_renew);
    }

    public function testPreferencesShowReadsPreferenceRecordFromDatabase(): void
    {
        $subscription = $this->memberSubscription();
        MemberSubscriptionPreference::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'is_active' => true,
            'email_notifications' => true,
            'newsletter_frequency' => 'weekly',
            'content_types' => ['magazine'],
            'category_preferences' => ['business'],
            'unsubscribe_token' => bin2hex(random_bytes(16)),
        ]);

        $response = $this->getAccount("/press-stack/account/subscriptions/{$subscription->id}/preferences");
        $data = $this->responseJson($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertSame('weekly', $data['preferences']['newsletter_frequency']);
        $this->assertSame(['business'], $data['preferences']['category_preferences']);
    }

    public function testPreferencesUpdatePersistsToDatabase(): void
    {
        $subscription = $this->memberSubscription();

        $response = $this->postAccount("/press-stack/account/subscriptions/{$subscription->id}/preferences", [
            'email_notifications' => '0',
            'newsletter_frequency' => 'monthly',
        ]);

        $preference = MemberSubscriptionPreference::where('member_id', $this->member->id)
            ->where('site_id', $subscription->site_id)
            ->first();
        $data = $this->responseJson($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertNotNull($preference);
        $this->assertFalse((bool) $preference->email_notifications);
        $this->assertSame('monthly', $preference->newsletter_frequency);
    }

    public function testDeliveryAddressIndexReturnsShippingAddressesFromDatabase(): void
    {
        $subscription = $this->memberSubscription([
            'delivery_type' => SubscriptionType::PRINTED->value,
        ]);
        $address = $this->memberAddress([
            'type' => 'shipping',
            'address_line_1' => '1 Database Street',
            'is_default' => true,
        ]);

        $response = $this->getAccount("/press-stack/account/subscriptions/{$subscription->id}/delivery-addresses");
        $data = $this->responseJson($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertSame($address->id, $data['addresses'][0]['id']);
        $this->assertSame('1 Database Street', $data['addresses'][0]['address_line_1']);
    }

    public function testSetDefaultDeliveryAddressPersistsToDatabase(): void
    {
        $subscription = $this->memberSubscription([
            'delivery_type' => SubscriptionType::PRINTED->value,
        ]);
        $address = $this->memberAddress([
            'type' => 'shipping',
            'is_default' => false,
        ]);

        $response = $this->postAccount("/press-stack/account/subscriptions/{$subscription->id}/delivery-addresses/{$address->id}/default");

        $address = Address::find($address->id);
        $data = $this->responseJson($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertTrue((bool) $address->is_default);
    }

    public function testIssueDeliveriesRejectsDigitalSubscriptionsUsingDatabaseRecord(): void
    {
        $subscription = $this->memberSubscription([
            'delivery_type' => SubscriptionType::DIGITAL->value,
        ]);

        $response = $this->getAccount("/press-stack/account/subscriptions/{$subscription->id}/issue-deliveries");

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Subscription not found.', $this->responseJson($response)['message']);
    }

    public function testUpgradeOptionsReturnsDatabaseBackedResponseForOwnedSubscription(): void
    {
        $subscription = $this->memberSubscription([
            'delivery_type' => SubscriptionType::PRINTED->value,
            'premium_access' => [],
        ]);

        $response = $this->getAccount("/press-stack/account/subscriptions/{$subscription->id}/upgrades");
        $data = $this->responseJson($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('upgrade', $data);
    }

    public function testUpgradePreviewRequiresValidUpgradePlanBeforeAnyPaymentWork(): void
    {
        $subscription = $this->memberSubscription();

        $response = $this->postAccount("/press-stack/account/subscriptions/{$subscription->id}/upgrades/preview", [
            'upgrade_plan_id' => '0',
        ]);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('A valid upgrade plan is required.', $this->responseJson($response)['message']);
    }

    private function memberSubscription(array $overrides = []): Subscription
    {
        $plan = $this->createSubscriptionPlan();

        return $this->createSubscription(array_merge([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'status' => 'active',
            'delivery_type' => SubscriptionType::DIGITAL->value,
            'price' => 10.00,
            'currency' => 'GBP',
            'auto_renew' => true,
            'start_date' => now_datetime()->modify('-1 day'),
            'end_date' => now_datetime()->modify('+1 month'),
            'next_billing_date' => now_datetime()->modify('+1 month'),
            'payment_subscription_id' => null,
            'type' => 'free',
        ], $overrides));
    }

    private function memberOrder(array $overrides = []): Order
    {
        return $this->createOrder(array_merge([
            'user_id' => $this->member->id,
            'member_id' => $this->member->id,
            'order_number' => 'SHOP-ACCOUNT-ORDER-' . uniqid(),
            'status' => 'pending',
            'total_amount' => 19.99,
            'currency' => 'GBP',
        ], $overrides));
    }

    private function memberAddress(array $overrides = []): Address
    {
        return $this->createAddress(array_merge([
            'member_id' => $this->member->id,
            'address_line_1' => '1 Test Street',
            'city' => 'London',
            'postcode' => 'SW1A 1AA',
            'country' => 'GB',
            'type' => 'shipping',
            'is_default' => false,
        ], $overrides));
    }

    private function getAccount(string $uri): Response
    {
        return $this->makeRequest('GET', $uri, [], $this->getDefaultHeaders(['Accept' => 'application/json'], true));
    }

    private function postAccount(string $uri, array $data = []): Response
    {
        return $this->makeRequest('POST', $uri, $data, $this->getDefaultHeaders(['Accept' => 'application/json'], true));
    }

    private function responseJson(Response $response): array
    {
        $decoded = json_decode($response->getContent(), true);

        $this->assertIsArray($decoded, 'Expected controller response to contain JSON. Body: ' . $response->getContent());

        return $decoded;
    }
}
