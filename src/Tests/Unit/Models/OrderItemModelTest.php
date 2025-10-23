<?php

namespace App\Tests\Unit\Models;

use App\Models\OrderItem;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use PHPUnit\Framework\TestCase;

class OrderItemModelTest extends FunctionalTestCase
{
    public function testOrderItemHasCorrectTable()
    {
        $item = new OrderItem();
        $this->assertEquals('order_items', $item->getTable());
    }

    public function testGetFormattedTotalAttributeReturnsFormattedString()
    {
        $item = new OrderItem([
            'total' => 50.00
        ]);

        // Without order relation, defaults to USD
        $formatted = $item->getFormattedTotalAttribute();
        $this->assertStringContainsString('50.00', $formatted);
    }
}