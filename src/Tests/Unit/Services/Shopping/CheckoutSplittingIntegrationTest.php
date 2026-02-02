<?php

namespace App\Tests\Unit\Services\Shopping;

use App\Services\Billing\CheckoutSplittingService;
use App\Services\Billing\PaymentAllocationService;
use App\Services\Shopping\MerchantShippingService;
use PHPUnit\Framework\TestCase;

/**
 * Feature-level tests that wire CheckoutSplittingService →
 * MerchantShippingService → PaymentAllocationService together,
 * verifying the full split → ship → allocate pipeline.
 */
class CheckoutSplittingIntegrationTest extends TestCase
{
    private CheckoutSplittingService $splitter;
    private MerchantShippingService $shipper;
    private PaymentAllocationService $allocator;

    protected function setUp(): void
    {
        $this->splitter = new CheckoutSplittingService();
        $this->shipper = new MerchantShippingService();
        $this->allocator = new PaymentAllocationService();
    }

    /**
     * 3 merchants, one system item, bundle spanning two merchants.
     * Verifies:
     *   - Correct number of order groups
     *   - Each group contains only its merchant's items
     *   - Shipping is per-group and respects threshold independently
     *   - Allocations sum to checkout total
     *   - Bundle items are traceable via metadata
     */
    public function testFullPipelineMultiMerchantWithBundleAndSystemItems(): void
    {
        $items = [
            // Merchant A: two regular items (subtotal 80 < 100 → shipping charged)
            ['product_id' => 1, 'product_name' => 'A1', 'quantity' => 1, 'price' => 50.00, 'merchant_id' => 10],
            ['product_id' => 2, 'product_name' => 'A2', 'quantity' => 1, 'price' => 30.00, 'merchant_id' => 10],

            // Merchant B: one item over threshold (subtotal 120 ≥ 100 → free shipping)
            ['product_id' => 3, 'product_name' => 'B1', 'quantity' => 1, 'price' => 120.00, 'merchant_id' => 20],

            // Bundle spanning merchants A and C
            ['product_id' => 4, 'product_name' => 'Bundle-A', 'quantity' => 1, 'price' => 25.00, 'merchant_id' => 10, 'bundle_id' => 'bndl-x'],
            ['product_id' => 5, 'product_name' => 'Bundle-C', 'quantity' => 1, 'price' => 35.00, 'merchant_id' => 30, 'bundle_id' => 'bndl-x'],

            // System item (no merchant)
            ['product_id' => 6, 'product_name' => 'System', 'quantity' => 2, 'price' => 10.00, 'merchant_id' => null],
        ];

        // --- Split ---
        $groups = $this->splitter->splitByMerchant($items);

        // 4 groups: merchants 10, 20, 30, and __system__
        $this->assertCount(4, $groups);
        $this->assertArrayHasKey(10, $groups);
        $this->assertArrayHasKey(20, $groups);
        $this->assertArrayHasKey(30, $groups);
        $this->assertArrayHasKey('__system__', $groups);

        // Merchant 10: items 1, 2, 4 (bundle part)
        $this->assertCount(3, $groups[10]['items']);
        // Merchant 20: item 3
        $this->assertCount(1, $groups[20]['items']);
        // Merchant 30: item 5 (bundle part)
        $this->assertCount(1, $groups[30]['items']);
        // System: item 6
        $this->assertCount(1, $groups['__system__']['items']);

        // Bundle traceability
        $bundleAItem = $groups[10]['items'][2]; // the bundle item added to merchant 10
        $bundleCItem = $groups[30]['items'][0]; // the bundle item in merchant 30
        $this->assertEquals('bndl-x', $bundleAItem['metadata']['bundle_id']);
        $this->assertEquals('bndl-x', $bundleCItem['metadata']['bundle_id']);

        $this->assertEquals('merchant_10', $groups[10]['stripe_group_key']);
        $this->assertEquals('merchant_20', $groups[20]['stripe_group_key']);
        $this->assertEquals('merchant_30', $groups[30]['stripe_group_key']);
        $this->assertEquals('system', $groups['__system__']['stripe_group_key']);

        // --- Shipping ---
        // Merchant 10 subtotal: 50 + 30 + 25 = 105 ≥ 100 → free
        // Merchant 20 subtotal: 120 ≥ 100 → free
        // Merchant 30 subtotal: 35 < 100 → charged (US = 10)
        // System subtotal: 20 < 100 → charged (US = 10)
        $shipping = $this->shipper->calculatePerGroup($groups, 'US');

        $this->assertEquals(0.00, $shipping[10]);
        $this->assertEquals(0.00, $shipping[20]);
        $this->assertEquals(10.00, $shipping[30]);
        $this->assertEquals(10.00, $shipping['__system__']);

        $totalShipping = array_sum($shipping); // 20.00

        // --- Allocation ---
        // Grand subtotal: 105 + 120 + 35 + 20 = 280
        $checkoutTotals = [
            'subtotal' => 280.00,
            'tax' => 28.00,   // 10% for illustration
            'shipping' => $totalShipping,
            'discount' => 0.00,
            'total' => 280.00 + 28.00 + $totalShipping,  // 328.00
        ];

        $allocations = $this->allocator->allocate($groups, $checkoutTotals, $shipping);

        // Verify each group's subtotal
        $this->assertEquals(105.00, $allocations[10]['subtotal']);
        $this->assertEquals(120.00, $allocations[20]['subtotal']);
        $this->assertEquals(35.00, $allocations[30]['subtotal']);
        $this->assertEquals(20.00, $allocations['__system__']['subtotal']);

        // Verify shipping isolation
        $this->assertEquals(0.00, $allocations[10]['shipping']);
        $this->assertEquals(0.00, $allocations[20]['shipping']);
        $this->assertEquals(10.00, $allocations[30]['shipping']);
        $this->assertEquals(10.00, $allocations['__system__']['shipping']);

        // Verify tax proportions (105/280, 120/280, 35/280, 20/280 of 28.00)
        $this->assertEquals(round(28.00 * 105 / 280, 2), $allocations[10]['tax']);
        $this->assertEquals(round(28.00 * 120 / 280, 2), $allocations[20]['tax']);
        // Last group gets remainder — just check it's reasonable
        $this->assertGreaterThan(0, $allocations[30]['tax']);
        $this->assertGreaterThan(0, $allocations['__system__']['tax']);

        // Verify grand total across all allocations equals checkout total
        $sumOfTotals = array_sum(array_column($allocations, 'total'));
        $this->assertEquals($checkoutTotals['total'], $sumOfTotals);
    }

    /**
     * All items belong to one merchant → single order, no splitting.
     */
    public function testSingleMerchantProducesSingleGroup(): void
    {
        $items = [
            ['product_id' => 1, 'product_name' => 'X', 'quantity' => 2, 'price' => 40.00, 'merchant_id' => 99],
            ['product_id' => 2, 'product_name' => 'Y', 'quantity' => 1, 'price' => 30.00, 'merchant_id' => 99],
        ];

        $groups = $this->splitter->splitByMerchant($items);
        $this->assertCount(1, $groups);
        $this->assertArrayHasKey(99, $groups);

        $shipping = $this->shipper->calculatePerGroup($groups, 'US');
        // subtotal = 80+30 = 110 ≥ 100 → free
        $this->assertEquals(0.00, $shipping[99]);

        $checkoutTotals = ['subtotal' => 110.00, 'tax' => 11.00, 'shipping' => 0.00, 'discount' => 0.00, 'total' => 121.00];
        $allocations = $this->allocator->allocate($groups, $checkoutTotals, $shipping);

        $this->assertEquals(110.00, $allocations[99]['subtotal']);
        $this->assertEquals(121.00, $allocations[99]['total']);
    }

    /**
     * Only system items → single system order.
     */
    public function testOnlySystemItemsProduceSingleSystemGroup(): void
    {
        $items = [
            ['product_id' => 1, 'product_name' => 'S1', 'quantity' => 1, 'price' => 20.00, 'merchant_id' => null],
            ['product_id' => 2, 'product_name' => 'S2', 'quantity' => 3, 'price' => 10.00, 'merchant_id' => null],
        ];

        $groups = $this->splitter->splitByMerchant($items);
        $this->assertCount(1, $groups);
        $this->assertArrayHasKey('__system__', $groups);
        $this->assertTrue($this->splitter->hasSystemOrder($groups));
        $this->assertCount(0, $this->splitter->getMerchantKeys($groups));

        $shipping = $this->shipper->calculatePerGroup($groups, 'US');
        // subtotal = 20 + 30 = 50 < 100 → charged
        $this->assertEquals(10.00, $shipping['__system__']);

        $checkoutTotals = ['subtotal' => 50.00, 'tax' => 5.00, 'shipping' => 10.00, 'discount' => 0.00, 'total' => 65.00];
        $allocations = $this->allocator->allocate($groups, $checkoutTotals, $shipping);

        $this->assertEquals(50.00, $allocations['__system__']['subtotal']);
        $this->assertEquals(65.00, $allocations['__system__']['total']);
    }

    /**
     * Discount is distributed proportionally; no group gets a negative total.
     */
    public function testLargeDiscountDistributedProportionally(): void
    {
        $items = [
            ['product_id' => 1, 'product_name' => 'A', 'quantity' => 1, 'price' => 80.00, 'merchant_id' => 10],
            ['product_id' => 2, 'product_name' => 'B', 'quantity' => 1, 'price' => 20.00, 'merchant_id' => 20],
        ];

        $groups = $this->splitter->splitByMerchant($items);
        $shipping = $this->shipper->calculatePerGroup($groups, 'US');

        // Both under 100 threshold: each charged 10
        $this->assertEquals(10.00, $shipping[10]);
        $this->assertEquals(10.00, $shipping[20]);

        // Big discount (but not exceeding subtotal)
        $checkoutTotals = [
            'subtotal' => 100.00,
            'tax' => 0.00,
            'shipping' => 20.00,
            'discount' => 40.00,   // 40% of subtotal
            'total' => 80.00,   // 100 + 20 - 40
        ];

        $allocations = $this->allocator->allocate($groups, $checkoutTotals, $shipping);

        // Merchant 10 (80%): discount = 32
        $this->assertEquals(32.00, $allocations[10]['discount']);
        // Merchant 20 (20%): discount = 8
        $this->assertEquals(8.00, $allocations[20]['discount']);

        // Totals should be positive
        $this->assertGreaterThanOrEqual(0, $allocations[10]['total']);
        $this->assertGreaterThanOrEqual(0, $allocations[20]['total']);

        // Sum = checkout total
        $sum = $allocations[10]['total'] + $allocations[20]['total'];
        $this->assertEquals($checkoutTotals['total'], $sum);
    }

    /**
     * A fully-discounted system bucket produces a zero-total allocation
     * that is correctly flagged as not Stripe-eligible, while merchant
     * orders with positive totals remain eligible.
     */
    public function testZeroTotalGroupIsMarkedNotStripeEligible(): void
    {
        $items = [
            ['product_id' => 1, 'product_name' => 'M', 'quantity' => 1, 'price' => 100.00, 'merchant_id' => 10],
            ['product_id' => 2, 'product_name' => 'S', 'quantity' => 1, 'price' => 0.00, 'merchant_id' => null],
        ];

        $groups = $this->splitter->splitByMerchant($items);
        $shipping = $this->shipper->calculatePerGroup($groups, 'US');

        // System item has £0 subtotal, and system shipping will be charged (0 < 100 threshold → £10).
        // We'll set checkout shipping to match only the merchant's (£0, over threshold) to force
        // the system bucket total to exactly £0 by giving it £0 shipping allocation explicitly.
        $shippingOverride = [10 => 0.00, '__system__' => 0.00];

        $checkoutTotals = [
            'subtotal' => 100.00,
            'tax' => 0.00,
            'shipping' => 0.00,
            'discount' => 0.00,
            'total' => 100.00,
        ];

        $allocations = $this->allocator->allocate($groups, $checkoutTotals, $shippingOverride);

        // Merchant order: £100 subtotal, positive total, eligible
        $this->assertTrue($allocations[10]['stripe_eligible']);
        $this->assertGreaterThan(0, $allocations[10]['total']);

        // System order: £0 subtotal, £0 shipping, £0 total, not eligible
        $this->assertEquals(0.00, $allocations['__system__']['total']);
        $this->assertFalse($allocations['__system__']['stripe_eligible']);
    }
}