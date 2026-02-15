<?php

namespace App\Tests\Unit\Services\Billing\Refunds;

use App\Services\Billing\Refund\RefundAmountCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RefundAmountCalculatorTest extends TestCase
{
    private RefundAmountCalculator $calculator;

    public function testCalculatesAmountFromExplicitRefundAmount(): void
    {
        $items = [
            [
                'product_name' => 'Product 1',
                'quantity' => 2,
                'refund_quantity' => 1,
                'unit_price' => 50.00,
                'refund_amount' => 45.00 // Explicit amount takes precedence
            ]
        ];

        $result = $this->calculator->calculateFromItems($items);

        $this->assertEquals(45.00, $result);
    }

    public function testCalculatesAmountFromQuantityAndPrice(): void
    {
        $items = [
            [
                'product_name' => 'Product 1',
                'quantity' => 3,
                'refund_quantity' => 2,
                'unit_price' => 25.00
            ]
        ];

        $result = $this->calculator->calculateFromItems($items);

        $this->assertEquals(50.00, $result); // 2 * 25.00
    }

    public function testCalculatesAmountForMultipleItems(): void
    {
        $items = [
            [
                'product_name' => 'Product 1',
                'quantity' => 2,
                'refund_quantity' => 1,
                'unit_price' => 50.00,
                'refund_amount' => 50.00
            ],
            [
                'product_name' => 'Product 2',
                'quantity' => 1,
                'refund_quantity' => 1,
                'unit_price' => 30.00,
                'refund_amount' => 30.00
            ],
            [
                'product_name' => 'Product 3',
                'quantity' => 5,
                'refund_quantity' => 2,
                'unit_price' => 10.00
            ]
        ];

        $result = $this->calculator->calculateFromItems($items);

        $this->assertEquals(100.00, $result); // 50 + 30 + 20
    }

    public function testReturnsZeroForEmptyItems(): void
    {
        $result = $this->calculator->calculateFromItems([]);

        $this->assertEquals(0.0, $result);
    }

    public function testUsesQuantityAsDefaultForRefundQuantity(): void
    {
        $items = [
            [
                'product_name' => 'Product 1',
                'quantity' => 3,
                'unit_price' => 15.00
                // No refund_quantity specified
            ]
        ];

        $result = $this->calculator->calculateFromItems($items);

        $this->assertEquals(45.00, $result); // 3 * 15.00
    }

    public function testThrowsExceptionWhenRefundQuantityExceedsOrderQuantity(): void
    {
        $items = [
            [
                'product_name' => 'Product 1',
                'quantity' => 2,
                'refund_quantity' => 5, // Exceeds quantity
                'unit_price' => 50.00
            ]
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Refund quantity (5) cannot exceed order quantity (2)');

        $this->calculator->calculateFromItems($items);
    }

    public function testThrowsExceptionWhenProductNameMissing(): void
    {
        $items = [
            [
                'quantity' => 2,
                'refund_quantity' => 1,
                'unit_price' => 50.00
                // Missing product_name
            ]
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Item missing required field: product_name');

        $this->calculator->calculateFromItems($items);
    }

    public function testHandlesZeroRefundQuantity(): void
    {
        $items = [
            [
                'product_name' => 'Product 1',
                'quantity' => 2,
                'refund_quantity' => 0,
                'unit_price' => 50.00
            ]
        ];

        $result = $this->calculator->calculateFromItems($items);

        $this->assertEquals(0.0, $result);
    }

    public function testHandlesZeroUnitPrice(): void
    {
        $items = [
            [
                'product_name' => 'Free Product',
                'quantity' => 5,
                'refund_quantity' => 5,
                'unit_price' => 0.00
            ]
        ];

        $result = $this->calculator->calculateFromItems($items);

        $this->assertEquals(0.0, $result);
    }

    public function testIgnoresNegativeRefundAmount(): void
    {
        $items = [
            [
                'product_name' => 'Product 1',
                'quantity' => 2,
                'refund_quantity' => 2,
                'unit_price' => 25.00,
                'refund_amount' => -10.00 // Negative, should fallback to calculation
            ]
        ];

        $result = $this->calculator->calculateFromItems($items);

        $this->assertEquals(50.00, $result); // Falls back to 2 * 25.00
    }

    public function testHandlesDecimalAmounts(): void
    {
        $items = [
            [
                'product_name' => 'Product 1',
                'quantity' => 3,
                'refund_quantity' => 2,
                'unit_price' => 12.99
            ]
        ];

        $result = $this->calculator->calculateFromItems($items);

        $this->assertEquals(25.98, $result); // 2 * 12.99
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new RefundAmountCalculator();
    }
}