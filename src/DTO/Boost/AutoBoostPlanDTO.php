<?php

namespace App\DTO\Boost;

final class AutoBoostPlanDTO
{
    /**
     * @param BoostAllocationDTO[] $allocations
     */
    public function __construct(
        public readonly int   $merchantId,
        public readonly float $totalBudget,
        public readonly float $totalAllocated,
        public readonly float $remaining,
        public readonly array $allocations,
        public readonly bool  $isDryRun,
    )
    {
    }

    public function toArray(): array
    {
        return [
            'merchant_id' => $this->merchantId,
            'total_budget' => $this->totalBudget,
            'total_allocated' => $this->totalAllocated,
            'remaining' => $this->remaining,
            'is_dry_run' => $this->isDryRun,
            'allocations' => array_map(fn($a) => $a->toArray(), $this->allocations),
        ];
    }
}