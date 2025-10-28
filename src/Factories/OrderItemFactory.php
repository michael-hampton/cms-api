<?php

namespace App\Factories;

use App\Framework\Tests\Factories\Factory;
use App\Models\OrderItem;
use App\Models\ProductVariant;

class OrderItemFactory extends Factory
{
    protected function model(): string
    {
        return OrderItem::class;
    }

    protected function definition(): array
    {
        return [
            'order_id' => null,
            'product_name' => 'Old Product',
            'product_sku' => 'OLD-001',
            'quantity' => 1,
            'unit_price' => 100.00,
            'subtotal' => 100.00,
            'total' => 100.00
        ];
    }

    public function forOrder(int $orderId): static
    {
        return $this->state(['order_id' => $orderId]);
    }
}