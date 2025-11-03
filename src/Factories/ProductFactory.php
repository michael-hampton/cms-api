<?php

namespace App\Factories;

use App\Framework\Tests\Factories\Factory;
use App\Framework\Tests\Factories\HasSiteId;
use App\Models\Product;

class ProductFactory extends Factory
{
    use HasSiteId;

    protected function model(): string
    {
        return Product::class;
    }

    protected function definition(): array
    {
        return $this->withSiteId([
            'slug' => 'test-product-' . uniqid(),
            'name' => 'Test Product',
            'description' => 'Test description',
            'price' => 99.99,
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Custom state: inactive product
     */
    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /**
     * Custom state: with specific price
     */
    public function priced(float $price): static
    {
        return $this->state(['price' => $price]);
    }
}