<?php

namespace App\Factories;

use App\Framework\Tests\Factories\Factory;
use App\Models\ProductPriceHistory;

class ProductPriceHistoryFactory extends Factory
{
    protected function model(): string
    {
        return ProductPriceHistory::class;
    }

    protected function definition(): array
    {
        return [
            'product_id' => 1,
            'product_merchant_id' => null,
            'price' => 99.99,
            'recorded_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function forProduct(int $productId): static
    {
        return $this->state(['product_id' => $productId]);
    }

    public function forMerchant(int $merchantId): static
    {
        return $this->state(['product_merchant_id' => $merchantId]);
    }

    public function priced(float $price): static
    {
        return $this->state(['price' => $price]);
    }

    public function recordedAt(string $datetime): static
    {
        return $this->state(['recorded_at' => $datetime]);
    }
}