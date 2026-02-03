<?php

namespace App\Factories;

use App\Framework\Tests\Factories\Factory;
use App\Framework\Tests\Factories\HasSiteId;
use App\Models\Order;

class OrderFactory extends Factory
{
    use HasSiteId;

    protected function model(): string
    {
        return Order::class;
    }

    protected function definition(): array
    {
        return $this->withSiteId([
            'order_number' => 'ORD-' . uniqid(),
            'status' => 'pending',
            'payment_status' => 'pending',
            'total' => 100.00,
            'site_id' => $this->siteId
        ]);
    }

    public function named(string $name): static
    {
        return $this->state([
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)) . '-' . uniqid(),
        ]);
    }
}