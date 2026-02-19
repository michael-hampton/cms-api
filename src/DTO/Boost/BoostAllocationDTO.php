<?php

namespace App\DTO\Boost;

final class BoostAllocationDTO
{
    public function __construct(
        public readonly int    $productId,
        public readonly string $productName,
        public readonly string $boostableType,
        public readonly string $context,
        public readonly float  $multiplier,
        public readonly float  $cost,
        public readonly float  $opportunityScore,
        public readonly string $goal,
    )
    {
    }

    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'product_name' => $this->productName,
            'boostable_type' => $this->boostableType,
            'context' => $this->context,
            'multiplier' => $this->multiplier,
            'cost' => $this->cost,
            'opportunity_score' => $this->opportunityScore,
            'goal' => $this->goal,
        ];
    }
}