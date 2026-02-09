<?php

namespace App\Services\Newsletter\DTOs\BlockData;

class ProductComparisonBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string $title,
        public readonly string $productA,
        public readonly string $productB,
        public readonly array  $comparisons
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? '',
            productA: $data['productA'] ?? '',
            productB: $data['productB'] ?? '',
            comparisons: $data['comparisons'] ?? []
        );
    }
}