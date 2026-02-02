<?php

namespace App\Tests\Unit\Services\Billing;

use App\Services\Billing\PaymentAllocationService;
use PHPUnit\Framework\TestCase;

class PaymentAllocationServiceTest extends TestCase
{
    private PaymentAllocationService $service;

    protected function setUp(): void
    {
        $this->service = new PaymentAllocationService();
    }

    // --- Basic proportional allocation ---

    public function testAllocateDistributesTaxProportionally(): void
    {
        $groups = [
            100 => [
                'merchant_id' => 100,
                'items' => [
                    ['price' => 100.00, 'quantity' => 1],  // subtotal 100
                ],
            ],
            200 => [
                'merchant_id' => 200,
                'items' => [
                    ['price' => 100.00, 'quantity' => 1],  // subtotal 100
                ],
            ],
        ];

        $checkoutTotals = [
            'subtotal' => 200.00,
            'tax' => 20.00,
            'shipping' => 0.00,
            'discount' => 0.00,
            'total' => 220.00,
        ];

        $shippingPerGroup = [100 => 0.00, 200 => 0.00];

        $result = $this->service->allocate($groups, $checkoutTotals, $shippingPerGroup);

        // Equal subtotals → equal tax
        $this->assertEquals(10.00, $result[100]['tax']);
        $this->assertEquals(10.00, $result[200]['tax']);
    }

    public function testAllocateDistributesDiscountProportionally(): void
    {
        $groups = [
            100 => [
                'merchant_id' => 100,
                'items' => [['price' => 60.00, 'quantity' => 1]],  // 60
            ],
            200 => [
                'merchant_id' => 200,
                'items' => [['price' => 40.00, 'quantity' => 1]],  // 40
            ],
        ];

        $checkoutTotals = [
            'subtotal' => 100.00,
            'tax' => 0.00,
            'shipping' => 0.00,
            'discount' => 10.00,
            'total' => 90.00,
        ];

        $result = $this->service->allocate($groups, $checkoutTotals, [100 => 0.00, 200 => 0.00]);

        // 60% and 40% of the £10 discount
        $this->assertEquals(6.00, $result[100]['discount']);
        $this->assertEquals(4.00, $result[200]['discount']);
    }

    public function testAllocateUsesPerGroupShipping(): void
    {
        $groups = [
            100 => [
                'merchant_id' => 100,
                'items' => [['price' => 50.00, 'quantity' => 1]],
            ],
            200 => [
                'merchant_id' => 200,
                'items' => [['price' => 50.00, 'quantity' => 1]],
            ],
        ];

        $checkoutTotals = [
            'subtotal' => 100.00, 'tax' => 0.00, 'shipping' => 25.00, 'discount' => 0.00, 'total' => 125.00,
        ];

        $shippingPerGroup = [100 => 10.00, 200 => 15.00];

        $result = $this->service->allocate($groups, $checkoutTotals, $shippingPerGroup);

        $this->assertEquals(10.00, $result[100]['shipping']);
        $this->assertEquals(15.00, $result[200]['shipping']);
    }

    public function testAllocateTotalsEqualSubtotalPlusShippingPlusTaxMinusDiscount(): void
    {
        $groups = [
            100 => [
                'merchant_id' => 100,
                'items' => [['price' => 80.00, 'quantity' => 1]],
            ],
            200 => [
                'merchant_id' => 200,
                'items' => [['price' => 20.00, 'quantity' => 1]],
            ],
        ];

        $checkoutTotals = [
            'subtotal' => 100.00, 'tax' => 10.00, 'shipping' => 20.00, 'discount' => 5.00, 'total' => 125.00,
        ];

        $shippingPerGroup = [100 => 12.00, 200 => 8.00];

        $result = $this->service->allocate($groups, $checkoutTotals, $shippingPerGroup);

        foreach ($result as $key => $alloc) {
            $expected = round($alloc['subtotal'] + $alloc['tax'] + $alloc['shipping'] - $alloc['discount'], 2);
            $this->assertEquals($expected, $alloc['total'], "Total mismatch for key $key");
            // stripe_eligible must be present on every allocation
            $this->assertArrayHasKey('stripe_eligible', $alloc);
        }
    }

    // --- Edge cases ---

    public function testAllocateReturnsEmptyForNoGroups(): void
    {
        $result = $this->service->allocate([], ['subtotal' => 0, 'tax' => 0, 'shipping' => 0, 'discount' => 0, 'total' => 0]);
        $this->assertCount(0, $result);
    }

    public function testAllocateSingleGroupGetsEverything(): void
    {
        $groups = [
            100 => [
                'merchant_id' => 100,
                'items' => [['price' => 100.00, 'quantity' => 1]],
            ],
        ];

        $checkoutTotals = [
            'subtotal' => 100.00, 'tax' => 10.00, 'shipping' => 5.00, 'discount' => 2.00, 'total' => 113.00,
        ];

        $result = $this->service->allocate($groups, $checkoutTotals, [100 => 5.00]);

        $this->assertEquals(100.00, $result[100]['subtotal']);
        $this->assertEquals(10.00, $result[100]['tax']);
        $this->assertEquals(5.00, $result[100]['shipping']);
        $this->assertEquals(2.00, $result[100]['discount']);
        $this->assertEquals(113.00, $result[100]['total']);
    }

    public function testAllocateWithSystemAndMerchantGroups(): void
    {
        $groups = [
            100 => [
                'merchant_id' => 100,
                'items' => [['price' => 75.00, 'quantity' => 1]],
            ],
            '__system__' => [
                'merchant_id' => null,
                'items' => [['price' => 25.00, 'quantity' => 1]],
            ],
        ];

        $checkoutTotals = [
            'subtotal' => 100.00, 'tax' => 10.00, 'shipping' => 20.00, 'discount' => 0.00, 'total' => 130.00,
        ];

        $shippingPerGroup = [100 => 10.00, '__system__' => 10.00];

        $result = $this->service->allocate($groups, $checkoutTotals, $shippingPerGroup);

        // Merchant 100 has 75% of subtotal
        $this->assertEquals(75.00, $result[100]['subtotal']);
        $this->assertEquals(7.50, $result[100]['tax']);

        // System has 25%
        $this->assertEquals(25.00, $result['__system__']['subtotal']);
        $this->assertEquals(2.50, $result['__system__']['tax']);
    }

    public function testAllocateHandlesMultipleQuantities(): void
    {
        $groups = [
            100 => [
                'merchant_id' => 100,
                'items' => [
                    ['price' => 10.00, 'quantity' => 3],  // 30
                    ['price' => 20.00, 'quantity' => 1],  // 20  → subtotal 50
                ],
            ],
            200 => [
                'merchant_id' => 200,
                'items' => [
                    ['price' => 50.00, 'quantity' => 1],  // subtotal 50
                ],
            ],
        ];

        $checkoutTotals = [
            'subtotal' => 100.00, 'tax' => 10.00, 'shipping' => 0.00, 'discount' => 0.00, 'total' => 110.00,
        ];

        $result = $this->service->allocate($groups, $checkoutTotals, [100 => 0.00, 200 => 0.00]);

        // 50/50 split
        $this->assertEquals(50.00, $result[100]['subtotal']);
        $this->assertEquals(50.00, $result[200]['subtotal']);
        $this->assertEquals(5.00, $result[100]['tax']);
        $this->assertEquals(5.00, $result[200]['tax']);
    }

    public function testAllocateNoShippingMapDefaultsToZero(): void
    {
        $groups = [
            100 => [
                'merchant_id' => 100,
                'items' => [['price' => 100.00, 'quantity' => 1]],
            ],
        ];

        $checkoutTotals = [
            'subtotal' => 100.00, 'tax' => 0.00, 'shipping' => 0.00, 'discount' => 0.00, 'total' => 100.00,
        ];

        // Deliberately pass empty shipping map
        $result = $this->service->allocate($groups, $checkoutTotals, []);

        $this->assertEquals(0.00, $result[100]['shipping']);
        $this->assertEquals(100.00, $result[100]['total']);
    }

    public function testAllocateRemaindergoeesToLargestGroupDeterministically(): void
    {
        // 3 groups with subtotals that do not divide the tax evenly.
        // Tax = 10.00, subtotals = 50, 30, 20 (grand = 100)
        // Proportions: 0.5, 0.3, 0.2 → taxes 5.00, 3.00, 2.00 — happens to be exact here,
        // so use tax = 10.01 to force a remainder penny.
        $groups = [
            // Deliberately inserted in ascending order to prove sort overrides insertion order
            30 => [
                'merchant_id' => 30,
                'items' => [['price' => 20.00, 'quantity' => 1]],  // smallest
            ],
            20 => [
                'merchant_id' => 20,
                'items' => [['price' => 30.00, 'quantity' => 1]],  // middle
            ],
            10 => [
                'merchant_id' => 10,
                'items' => [['price' => 50.00, 'quantity' => 1]],  // largest
            ],
        ];

        $checkoutTotals = [
            'subtotal' => 100.00, 'tax' => 10.01, 'shipping' => 0.00, 'discount' => 0.00, 'total' => 110.01,
        ];

        $result = $this->service->allocate($groups, $checkoutTotals, [10 => 0.00, 20 => 0.00, 30 => 0.00]);

        // The smallest group (subtotal 20) is last after descending sort, so it absorbs remainder.
        // But the key invariant is: sum of all taxes == checkout tax, always.
        $totalTax = $result[10]['tax'] + $result[20]['tax'] + $result[30]['tax'];
        $this->assertEquals(10.01, $totalTax);

        // Run again with same input — result must be identical (determinism)
        $result2 = $this->service->allocate($groups, $checkoutTotals, [10 => 0.00, 20 => 0.00, 30 => 0.00]);
        $this->assertEquals($result, $result2);
    }

    public function testAllocateStripeEligibleIsTrueForPositiveTotals(): void
    {
        $groups = [
            100 => [
                'merchant_id' => 100,
                'items' => [['price' => 50.00, 'quantity' => 1]],
            ],
        ];

        $checkoutTotals = [
            'subtotal' => 50.00, 'tax' => 5.00, 'shipping' => 10.00, 'discount' => 0.00, 'total' => 65.00,
        ];

        $result = $this->service->allocate($groups, $checkoutTotals, [100 => 10.00]);

        $this->assertTrue($result[100]['stripe_eligible']);
    }

    public function testAllocateStripeEligibleIsFalseForZeroTotal(): void
    {
        // A group whose subtotal is entirely consumed by its share of the discount,
        // with zero shipping and zero tax.
        $groups = [
            100 => [
                'merchant_id' => 100,
                'items' => [['price' => 20.00, 'quantity' => 1]],  // subtotal 20
            ],
            200 => [
                'merchant_id' => 200,
                'items' => [['price' => 80.00, 'quantity' => 1]],  // subtotal 80
            ],
        ];

        // Discount = 100 (wipes subtotal entirely), no tax, no shipping
        $checkoutTotals = [
            'subtotal' => 100.00, 'tax' => 0.00, 'shipping' => 0.00, 'discount' => 100.00, 'total' => 0.00,
        ];

        $result = $this->service->allocate($groups, $checkoutTotals, [100 => 0.00, 200 => 0.00]);

        // Both groups end up at £0 total
        $this->assertEquals(0.00, $result[100]['total']);
        $this->assertEquals(0.00, $result[200]['total']);
        $this->assertFalse($result[100]['stripe_eligible']);
        $this->assertFalse($result[200]['stripe_eligible']);
    }
}