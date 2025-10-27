<?php

namespace App\Factories;

use App\Framework\Tests\Factories\Factory;
use App\Models\ProductSpecification;

class ProductSpecificationFactory extends Factory
{
    protected function model(): string
    {
        return ProductSpecification::class;
    }

    protected function definition(): array
    {
        return [
            'product_id' => null,
            'category' => 'General',
            'key' => 'weight',
            'value' => '1kg',
            'sort_order' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function forProduct(int $productId): static
    {
        return $this->state(['product_id' => $productId]);
    }

    public function inCategory(string $category): static
    {
        return $this->state(['category' => $category]);
    }

    public function withSpec(string $key, string $value): static
    {
        return $this->state(['key' => $key, 'value' => $value]);
    }
}