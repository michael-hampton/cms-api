<?php

namespace App\Factories;

use App\Framework\Tests\Factories\Factory;
use App\Models\ProductImage;

class ProductImageFactory extends Factory
{
    protected function model(): string
    {
        return ProductImage::class;
    }

    protected function definition(): array
    {
        return [
            'product_id' => null,
            'variant_id' => null,
            'url' => 'https://example.com/image.jpg',
            'alt' => 'Test image',
            'is_primary' => false,
            'sort_order' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function forProduct(int $productId): static
    {
        return $this->state(['product_id' => $productId]);
    }

    public function forVariant(int $variantId): static
    {
        return $this->state(['variant_id' => $variantId]);
    }

    public function primary(): static
    {
        return $this->state(['is_primary' => true]);
    }

    public function withUrl(string $url): static
    {
        return $this->state(['url' => $url]);
    }
}