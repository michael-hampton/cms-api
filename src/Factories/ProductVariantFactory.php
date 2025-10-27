<?php

namespace App\Factories;

use App\Framework\Tests\Factories\Factory;
use App\Models\ProductVariant;

class ProductVariantFactory extends Factory
{
    protected function model(): string
    {
        return ProductVariant::class;
    }

    protected function definition(): array
    {
        return [
            'product_id' => null,
            'sku' => 'SKU-' . uniqid(),
            'attributes' => json_encode(['color' => 'red', 'size' => 'M']),
            'price_modifier' => 0,
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function forProduct(int $productId): static
    {
        return $this->state(['product_id' => $productId]);
    }

    public function withAttributes(array $attributes): static
    {
        return $this->state(['attributes' => json_encode($attributes)]);
    }

    public function withPriceModifier(float $modifier): static
    {
        return $this->state(['price_modifier' => $modifier]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}