<?php

namespace App\Factories;

use App\Framework\Tests\Factories\Factory;
use App\Models\ProductMerchant;

class ProductMerchantFactory extends Factory
{
    protected function model(): string
    {
        return ProductMerchant::class;
    }

    protected function definition(): array
    {
        return [
            'product_id' => null,
            'url' => 'https://example.com',
            'price' => 99.99,
            'is_available' => true,
            'last_price_check' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function forProduct(int $productId): static
    {
        return $this->state(['product_id' => $productId]);
    }

    public function unavailable(): static
    {
        return $this->state(['is_available' => false]);
    }

    public function priced(float $price): static
    {
        return $this->state(['price' => $price]);
    }

    public function named(string $name): static
    {
        return $this->state(['name' => $name]);
    }
}