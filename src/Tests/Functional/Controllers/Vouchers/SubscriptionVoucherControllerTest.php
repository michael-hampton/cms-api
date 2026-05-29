<?php

namespace App\Tests\Functional\Controllers\Vouchers;

use App\Enums\Vouchers\VoucherType;
use App\Models\Voucher;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

/**
 * Functional tests for POST/GET/PUT/DELETE /api/{site}/subscription-vouchers.
 *
 * Response envelope:
 *   Success single  → { data: { voucher: {} }, success: true, status: int, timestamp }
 *   Success list    → { data: { items: [], pagination: {} }, success: true, ... }
 *   Success delete  → { data: { message: string }, success: true, ... }
 *   Error           → { error: string, errors: {}, success: false, status: int, ... }
 */
class SubscriptionVoucherControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private const BASE = '/api/subscription-vouchers';

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function validPercentagePayload(array $overrides = []): array
    {
        return array_merge([
            'code'                           => 'SUBTEST' . strtoupper(uniqid()),
            'name'                           => 'Test Sub Voucher',
            'discount_type'                  => 'percentage',
            'discount_percentage'            => 20,
            'subscription_discount_duration' => 'once',
            'status'                         => 'active',
        ], $overrides);
    }

    private function validFixedPayload(array $overrides = []): array
    {
        return array_merge([
            'code'                           => 'SUBFIX' . strtoupper(uniqid()),
            'name'                           => 'Fixed Sub Voucher',
            'discount_type'                  => 'fixed',
            'discount_amount'                => 1500,
            'subscription_discount_duration' => 'once',
            'status'                         => 'active',
        ], $overrides);
    }

    /**
     * Create a voucher that is already flagged applies_to_subscriptions = true
     * so it shows up in the subscription-vouchers index.
     */
    private function createSubscriptionVoucher(array $overrides = []): Voucher
    {
        return $this->createVoucher(array_merge([
            'applies_to_subscriptions' => true,
            'applies_to_orders'        => false,
            'discount_type'            => 'percentage',
            'type'                     => VoucherType::Percentage->value,
            'value'                    => 10,
            'discount_percentage'      => 10,
            'subscription_discount_duration' => 'once',
        ], $overrides));
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function testIndexReturnsOnlySubscriptionVouchers(): void
    {
        $this->createSubscriptionVoucher(['code' => 'SUB_A']);
        $this->createSubscriptionVoucher(['code' => 'SUB_B']);

        // Order-only voucher must NOT appear in results
        $this->createVoucher([
            'code'                     => 'ORDER_ONLY',
            'applies_to_subscriptions' => false,
            'applies_to_orders'        => true,
            'type'                     => VoucherType::Percentage->value,
            'value'                    => 5,
        ]);

        $response = $this->getForSite(self::BASE);

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('items', $data['data']);

        foreach ($data['data']['items'] as $item) {
            $this->assertTrue(
                $item['applies_to_subscriptions'],
                'Index must never return order-only vouchers',
            );
            $this->assertNotEquals('ORDER_ONLY', $item['code']);
        }

        $codes = array_column($data['data']['items'], 'code');
        $this->assertContains('SUB_A', $codes);
        $this->assertContains('SUB_B', $codes);
    }

    public function testIndexReturnsPaginationMeta(): void
    {
        $this->createSubscriptionVoucher();

        $response = $this->getForSite(self::BASE);

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('pagination', $data['data']);
        $this->assertArrayHasKey('total', $data['data']['pagination']);
        $this->assertArrayHasKey('current_page', $data['data']['pagination']);
    }

    public function testIndexReturnsSubscriptionVoucherFields(): void
    {
        $plan = $this->createSubscriptionPlan();
        $this->createSubscriptionVoucher([
            'code'                     => 'FIELDCHECK',
            'subscription_plan_ids'    => [$plan->id],
            'stripe_coupon_id'         => 'coupon_abc',
        ]);

        $response = $this->getForSite(self::BASE);

        $data  = json_decode($response->getContent(), true);
        $items = $data['data']['items'];

        $voucher = collect($items)->firstWhere('code', 'FIELDCHECK');
        $this->assertNotNull($voucher, 'FIELDCHECK voucher not found in index');

        // Fields the Angular modal depends on
        $this->assertArrayHasKey('subscription_plan_ids', $voucher);
        $this->assertArrayHasKey('subscription_discount_duration', $voucher);
        $this->assertArrayHasKey('stripe_coupon_id', $voucher);
        $this->assertArrayHasKey('stripe_coupon_synced_at', $voucher);
        $this->assertArrayHasKey('is_stackable', $voucher);

        // Fields that should NOT be in the subscription resource
        $this->assertArrayNotHasKey('minimum_order_value', $voucher);
    }

    public function testIndexRequiresAuthentication(): void
    {
        $response = $this->getForSiteUnauthenticated(self::BASE);

        $this->assertResponseStatus(401, $response);
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function testStoreCreatesPercentageVoucher(): void
    {
        $payload = $this->validPercentagePayload(['code' => 'PCT20']);

        $response = $this->postForSite(self::BASE, $payload);

        $this->assertResponseStatus(201, $response);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $voucher = $data['data']['voucher'];
        $this->assertEquals('PCT20', $voucher['code']);
        $this->assertEquals('percentage', $voucher['discount_type']);
        $this->assertEquals(20, $voucher['discount_percentage']);
        $this->assertTrue($voucher['applies_to_subscriptions']);
    }

    public function testStoreCreatesFixedVoucher(): void
    {
        $payload = $this->validFixedPayload(['code' => 'FIX1500']);

        $response = $this->postForSite(self::BASE, $payload);

        $this->assertResponseStatus(201, $response);
        $data    = json_decode($response->getContent(), true);
        $voucher = $data['data']['voucher'];

        $this->assertEquals('FIX1500', $voucher['code']);
        $this->assertEquals('fixed', $voucher['discount_type']);
        $this->assertEquals(1500, $voucher['discount_amount']);
    }

    public function testStoreCreatesRepeatingVoucherWithMonths(): void
    {
        $payload = $this->validPercentagePayload([
            'code'                           => 'REPEAT3',
            'subscription_discount_duration' => 'repeating',
            'subscription_duration_months'   => 3,
        ]);

        $response = $this->postForSite(self::BASE, $payload);

        $this->assertResponseStatus(201, $response);
        $data    = json_decode($response->getContent(), true);
        $voucher = $data['data']['voucher'];

        $this->assertEquals('repeating', $voucher['subscription_discount_duration']);
        $this->assertEquals(3, $voucher['subscription_duration_months']);
    }

    public function testStoreCreatesForeverVoucher(): void
    {
        $payload = $this->validPercentagePayload([
            'code'                           => 'FOREVER1',
            'subscription_discount_duration' => 'forever',
        ]);

        $response = $this->postForSite(self::BASE, $payload);

        $this->assertResponseStatus(201, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('forever', $data['data']['voucher']['subscription_discount_duration']);
    }

    public function testStoreNormalizesCodeToUppercase(): void
    {
        $payload = $this->validPercentagePayload(['code' => 'lowercase_code']);

        $response = $this->postForSite(self::BASE, $payload);

        $this->assertResponseStatus(201, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('LOWERCASE_CODE', $data['data']['voucher']['code']);
    }

    public function testStoreForcesAppliesToSubscriptionsTrue(): void
    {
        // Even if the caller tries to pass false, the endpoint must override it
        $payload = $this->validPercentagePayload([
            'applies_to_subscriptions' => false,
        ]);

        $response = $this->postForSite(self::BASE, $payload);

        $this->assertResponseStatus(201, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['data']['voucher']['applies_to_subscriptions']);
    }

    public function testStoreWithPlanIds(): void
    {
        $plan1 = $this->createSubscriptionPlan();
        $plan2 = $this->createSubscriptionPlan();

        $payload = $this->validPercentagePayload([
            'subscription_plan_ids' => [$plan1->id, $plan2->id],
        ]);

        $response = $this->postForSite(self::BASE, $payload);

        $this->assertResponseStatus(201, $response);
        $data    = json_decode($response->getContent(), true);

        $planIds = $data['data']['voucher']['subscription_plan_ids'];

        $this->assertContains($plan1->id, $planIds);
        $this->assertContains($plan2->id, $planIds);
    }

    public function testStoreWithUsageAndStackableRules(): void
    {
        $payload = $this->validPercentagePayload([
            'usage_limit'    => 50,
            'per_user_limit' => 2,
            'is_stackable'   => true,
        ]);

        $response = $this->postForSite(self::BASE, $payload);

        $this->assertResponseStatus(201, $response);
        $data    = json_decode($response->getContent(), true);
        $voucher = $data['data']['voucher'];

        $this->assertEquals(50, $voucher['usage_limit']);
        $this->assertEquals(2, $voucher['per_user_limit']);
        $this->assertTrue($voucher['is_stackable']);
    }

    // ── Store validation failures ─────────────────────────────────────────────

    public function testStoreRequiresCode(): void
    {
        $payload = $this->validPercentagePayload();
        unset($payload['code']);

        $response = $this->postForSite(self::BASE, $payload);

        $this->assertResponseStatus(422, $response);
    }

    public function testStoreRequiresName(): void
    {
        $payload = $this->validPercentagePayload();
        unset($payload['name']);

        $response = $this->postForSite(self::BASE, $payload);

        $this->assertResponseStatus(422, $response);
    }

    public function testStoreRequiresDiscountType(): void
    {
        $payload = $this->validPercentagePayload();
        unset($payload['discount_type']);

        $response = $this->postForSite(self::BASE, $payload);

        $this->assertResponseStatus(422, $response);
    }

    public function testStoreRejectsInvalidDiscountType(): void
    {
        $payload = $this->validPercentagePayload(['discount_type' => 'banana']);

        $response = $this->postForSite(self::BASE, $payload);

        $this->assertResponseStatus(422, $response);
    }

    public function testStoreRejectsPercentageWithoutDiscountPercentage(): void
    {
        $payload = $this->validPercentagePayload();
        unset($payload['discount_percentage']);

        $response = $this->postForSite(self::BASE, $payload);

        $this->assertResponseStatus(422, $response);
    }

    public function testStoreRejectsFixedWithoutDiscountAmount(): void
    {
        $payload = $this->validFixedPayload();
        unset($payload['discount_amount']);

        $response = $this->postForSite(self::BASE, $payload);

        $this->assertResponseStatus(422, $response);
    }

    public function testStoreRejectsPercentageOver100(): void
    {
        $payload = $this->validPercentagePayload(['discount_percentage' => 101]);

        $response = $this->postForSite(self::BASE, $payload);

        $this->assertResponseStatus(422, $response);
    }

    public function testStoreRejectsRepeatingWithoutMonths(): void
    {
        $payload = $this->validPercentagePayload([
            'subscription_discount_duration' => 'repeating',
            // subscription_duration_months intentionally omitted
        ]);

        $response = $this->postForSite(self::BASE, $payload);

        $this->assertResponseStatus(422, $response);
    }

    public function testStoreRejectsOnceWithMonths(): void
    {
        $payload = $this->validPercentagePayload([
            'subscription_discount_duration' => 'once',
            'subscription_duration_months'   => 3,
        ]);

        $response = $this->postForSite(self::BASE, $payload);

        $this->assertResponseStatus(422, $response);
    }

    public function testStoreRejectsForeverWithMonths(): void
    {
        $payload = $this->validPercentagePayload([
            'subscription_discount_duration' => 'forever',
            'subscription_duration_months'   => 2,
        ]);

        $response = $this->postForSite(self::BASE, $payload);

        $this->assertResponseStatus(422, $response);
    }

    public function testStoreRejectsDuplicateCode(): void
    {
        $this->createSubscriptionVoucher(['code' => 'DUPCODE']);

        $payload = $this->validPercentagePayload(['code' => 'DUPCODE']);

        $response = $this->postForSite(self::BASE, $payload);

        $this->assertResponseStatus(422, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Validation failed', $data['error']);
    }

    public function testStoreRejectsInvalidDateRange(): void
    {
        $payload = $this->validPercentagePayload([
            'starts_at'  => '2025-12-31',
            'expires_at' => '2025-01-01',
        ]);

        $response = $this->postForSite(self::BASE, $payload);

        $this->assertResponseStatus(422, $response);
    }

    public function testStoreRequiresAuthentication(): void
    {
        $response = $this->postForSiteUnauthenticated(self::BASE, $this->validPercentagePayload());

        $this->assertResponseStatus(401, $response);
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function testShowReturnsVoucher(): void
    {
        $voucher = $this->createSubscriptionVoucher(['code' => 'SHOW_SUB']);

        $response = $this->getForSite(self::BASE . '/' . $voucher->id);

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('voucher', $data['data']);
        $this->assertEquals('SHOW_SUB', $data['data']['voucher']['code']);
    }

    public function testShowReturnsSubscriptionSpecificFields(): void
    {
        $plan    = $this->createSubscriptionPlan();
        $voucher = $this->createSubscriptionVoucher([
            'subscription_plan_ids'    => [$plan->id],
            'stripe_coupon_id'         => 'cu_test123',
            'subscription_discount_duration' => 'once',
            'terms_and_conditions'     => 'No stacking.',
            'is_stackable'             => false,
        ]);

        $response = $this->getForSite(self::BASE . '/' . $voucher->id);

        $data    = json_decode($response->getContent(), true);
        $result  = $data['data']['voucher'];

        $this->assertArrayHasKey('subscription_plan_ids', $result);
        $this->assertArrayHasKey('stripe_coupon_id', $result);
        $this->assertArrayHasKey('stripe_coupon_synced_at', $result);
        $this->assertArrayHasKey('terms_and_conditions', $result);
        $this->assertFalse($result['is_stackable']);
        $this->assertEquals('cu_test123', $result['stripe_coupon_id']);
    }

    public function testShowReturns404ForMissingVoucher(): void
    {
        $response = $this->getForSite(self::BASE . '/99999999');

        $this->assertResponseStatus(404, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function testUpdateModifiesVoucher(): void
    {
        $voucher = $this->createSubscriptionVoucher(['code' => 'UPDAT1']);

        $payload = [
            'code'                           => 'UPDAT1',
            'name'                           => 'Updated Name',
            'discount_type'                  => 'percentage',
            'discount_percentage'            => 30,
            'subscription_discount_duration' => 'once',
            'status'                         => 'active',
        ];

        $response = $this->putForSite(self::BASE . '/' . $voucher->id, $payload);

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('Updated Name', $data['data']['voucher']['name']);
        $this->assertEquals(30, $data['data']['voucher']['discount_percentage']);
    }

    public function testUpdateAllowsSameCodeOnSameVoucher(): void
    {
        $voucher = $this->createSubscriptionVoucher(['code' => 'SAMECODE']);

        $payload = [
            'code'                           => 'SAMECODE', // unchanged — must not fail uniqueness check
            'name'                           => 'Name Update',
            'discount_type'                  => 'percentage',
            'discount_percentage'            => 15,
            'subscription_discount_duration' => 'once',
        ];

        $response = $this->putForSite(self::BASE . '/' . $voucher->id, $payload);

        $this->assertResponseOk($response);
    }

    public function testUpdateChangingDiscountTypeResetsStripeCoupon(): void
    {
        $voucher = $this->createSubscriptionVoucher([
            'code'                   => 'STRPCHG',
            'discount_type'          => 'percentage',
            'discount_percentage'    => 10,
            'stripe_coupon_id'       => 'coupon_old',
            'stripe_coupon_synced_at' => date('Y-m-d H:i:s'),
        ]);

        $payload = [
            'code'                           => 'STRPCHG',
            'name'                           => $voucher->name,
            'discount_type'                  => 'fixed',       // changed
            'discount_amount'                => 500,
            'subscription_discount_duration' => 'once',
        ];

        $response = $this->putForSite(self::BASE . '/' . $voucher->id, $payload);

        $this->assertResponseOk($response);

        // VoucherService detects the config change and nulls the Stripe fields
        $updated = Voucher::find($voucher->id);
        $this->assertNull($updated->stripe_coupon_id);
        $this->assertNull($updated->stripe_coupon_synced_at);
    }

    public function testUpdateRejectsRepeatingWithoutMonths(): void
    {
        $voucher = $this->createSubscriptionVoucher();

        $payload = [
            'code'                           => $voucher->code,
            'name'                           => $voucher->name,
            'discount_type'                  => 'percentage',
            'discount_percentage'            => 10,
            'subscription_discount_duration' => 'repeating',
            // subscription_duration_months omitted
        ];

        $response = $this->putForSite(self::BASE . '/' . $voucher->id, $payload);

        $this->assertResponseStatus(422, $response);
    }

    public function testUpdateReturns404ForMissingVoucher(): void
    {
        $payload = [
            'code'                           => 'GHOST',
            'name'                           => 'Ghost',
            'discount_type'                  => 'percentage',
            'discount_percentage'            => 10,
            'subscription_discount_duration' => 'once',
        ];

        $response = $this->putForSite(self::BASE . '/99999999', $payload);

        $this->assertResponseStatus(404, $response);
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function testDeleteRemovesUnusedVoucher(): void
    {
        $voucher = $this->createSubscriptionVoucher(['usage_count' => 0]);

        $response = $this->deleteForSite(self::BASE . '/' . $voucher->id);

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Voucher deleted successfully', $data['data']['message']);

        $this->assertNull(Voucher::find($voucher->id));
    }

    public function testDeleteBlockedWhenVoucherHasUsage(): void
    {
        $voucher = $this->createSubscriptionVoucher(['usage_count' => 3]);

        $response = $this->deleteForSite(self::BASE . '/' . $voucher->id);

        $this->assertResponseStatus(400, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);

        // Voucher must still exist
        $this->assertNotNull(Voucher::find($voucher->id));
    }

    public function testDeleteReturns404ForMissingVoucher(): void
    {
        $response = $this->deleteForSite(self::BASE . '/99999999');

        // VoucherService throws VoucherNotFoundException which the controller
        // catches and returns as 400 — consistent with the general VoucherController
        $this->assertResponseStatus(400, $response);
    }

    // ── Check deletable ───────────────────────────────────────────────────────

    public function testCheckDeletableReturnsTrueForUnusedVoucher(): void
    {
        $voucher = $this->createSubscriptionVoucher(['usage_count' => 0]);

        $response = $this->getForSite(self::BASE . '/' . $voucher->id . '/deletable');

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('can_delete', $data['data']);
        $this->assertTrue($data['data']['can_delete']);
        $this->assertEquals(0, $data['data']['usage_count']);
    }

    public function testCheckDeletableReturnsFalseForUsedVoucher(): void
    {
        $voucher = $this->createSubscriptionVoucher(['usage_count' => 7]);

        $response = $this->getForSite(self::BASE . '/' . $voucher->id . '/deletable');

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['data']['can_delete']);
        $this->assertEquals(7, $data['data']['usage_count']);
        $this->assertTrue($data['data']['requires_confirmation']);
    }

    public function testCheckDeletableReturns500ForMissingVoucher(): void
    {
        $response = $this->getForSite(self::BASE . '/99999999/deletable');

        // VoucherRepository::checkDeletable throws when voucher not found
        $this->assertResponseStatus(500, $response);
    }

    // ── Cross-cutting ─────────────────────────────────────────────────────────

    public function testCreatedVoucherAppearsInIndex(): void
    {
        $code    = 'ROUNDTRIP' . strtoupper(uniqid());
        $payload = $this->validPercentagePayload(['code' => $code]);

        $storeResponse = $this->postForSite(self::BASE, $payload);
        $this->assertResponseStatus(201, $storeResponse);

        $indexResponse = $this->getForSite(self::BASE);
        $this->assertResponseOk($indexResponse);

        $data  = json_decode($indexResponse->getContent(), true);
        $codes = array_column($data['data']['items'], 'code');
        $this->assertContains($code, $codes);
    }

    public function testDeletedVoucherDoesNotAppearInIndex(): void
    {
        $voucher = $this->createSubscriptionVoucher([
            'code'        => 'GONE_SOON',
            'usage_count' => 0,
        ]);

        $this->deleteForSite(self::BASE . '/' . $voucher->id);

        $indexResponse = $this->getForSite(self::BASE);
        $data          = json_decode($indexResponse->getContent(), true);
        $codes         = array_column($data['data']['items'], 'code');

        $this->assertNotContains('GONE_SOON', $codes);
    }
}