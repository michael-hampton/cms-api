<?php

namespace App\Tests\Unit\Services;

use App\Services\OrderCalculationService;
use PHPUnit\Framework\TestCase;

class OrderCalculationServiceTest extends TestCase
{
    private OrderCalculationService $service;

    protected function setUp(): void
    {
        $this->service = new OrderCalculationService();
    }

    public function testCalculateOrderTotalsWithItems()
    {
        $items = [
            ['unit_price' => 50.00, 'quantity' => 2, 'tax' => 10.00],
            ['unit_price' => 30.00, 'quantity' => 1, 'tax' => 3.00]
        ];

        $orderData = [
            'shipping' => 10.00,
            'discount' => 5.00
        ];

        $result = $this->service->calculateOrderTotals($items, $orderData);

        $this->assertEquals(130.00, $result['subtotal']); // (50*2) + (30*1)
        $this->assertEquals(13.00, $result['tax']); // 10 + 3
        $this->assertEquals(10.00, $result['shipping']);
        $this->assertEquals(5.00, $result['discount']);
        $this->assertEquals(148.00, $result['total']); // 130 + 13 + 10 - 5
    }

    public function testCalculateOrderTotalsWithoutItemTax()
    {
        $items = [
            ['unit_price' => 100.00, 'quantity' => 1]
        ];

        $orderData = [
            'shipping' => 10.00,
            'discount' => 0.00
        ];

        $result = $this->service->calculateOrderTotals($items, $orderData, true);

        $this->assertEquals(100.00, $result['subtotal']);
        $this->assertEquals(11.00, $result['tax']); // (100 + 10) * 0.1
        $this->assertEquals(121.00, $result['total']);
    }

    public function testCalculateOrderTotalsWithoutDefaultTaxRate()
    {
        $items = [
            ['unit_price' => 100.00, 'quantity' => 1]
        ];

        $orderData = [
            'shipping' => 10.00,
            'discount' => 0.00
        ];

        $result = $this->service->calculateOrderTotals($items, $orderData);

        $this->assertEquals(100.00, $result['subtotal']);
        $this->assertEquals(0, $result['tax']); // (100 + 10) * 0.1
        $this->assertEquals(110.00, $result['total']);
    }

    public function testCalculateItemTotal()
    {
        $item = [
            'unit_price' => 50.00,
            'quantity' => 2,
            'tax' => 5.00
        ];

        $result = $this->service->calculateItemTotal($item);

        $this->assertEquals(100.00, $result['subtotal']);
        $this->assertEquals(5.00, $result['tax']);
        $this->assertEquals(105.00, $result['total']);
    }

    public function testCalculateItemTotalWithoutTax()
    {
        $item = [
            'unit_price' => 50.00,
            'quantity' => 2
        ];

        $result = $this->service->calculateItemTotal($item);

        $this->assertEquals(100.00, $result['subtotal']);
        $this->assertEquals(0.00, $result['tax']);
        $this->assertEquals(100.00, $result['total']);
    }
}