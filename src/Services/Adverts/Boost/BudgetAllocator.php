<?php

namespace App\Services\Adverts\Boost;

use App\DTO\Boost\AutoBoostPlanDTO;
use App\DTO\Boost\BoostAllocationDTO;
use App\DTO\Boost\BoostSuggestionDTO;

class BudgetAllocator
{
    /**
     * Allocates budget across suggestions, descending by opportunity score.
     * Stops when budget is exhausted or all suggestions are processed.
     *
     * @param BoostSuggestionDTO[] $suggestions Already sorted descending by score
     */
    public function allocate(
        int    $merchantId,
        float  $availableBudget,
        array  $suggestions,
        string $goal,
        bool   $dryRun = false,
    ): AutoBoostPlanDTO
    {
        $allocations = [];
        $totalAllocated = 0.0;

        foreach ($suggestions as $suggestion) {
            $cost = $suggestion->estimatedCost;

            if ($cost <= 0) {
                continue;
            }

            if (($totalAllocated + $cost) > $availableBudget) {
                // Cannot afford this suggestion — skip and try smaller ones
                continue;
            }

            $allocations[] = new BoostAllocationDTO(
                productId: $suggestion->productId,
                productName: $suggestion->productName,
                boostableType: $suggestion->boostableType,
                context: $suggestion->suggestedContext,
                multiplier: $suggestion->suggestedMultiplier,
                cost: $cost,
                opportunityScore: $suggestion->opportunityScore,
                goal: $goal,
            );

            $totalAllocated += $cost;
        }

        return new AutoBoostPlanDTO(
            merchantId: $merchantId,
            totalBudget: $availableBudget,
            totalAllocated: round($totalAllocated, 2),
            remaining: round($availableBudget - $totalAllocated, 2),
            allocations: $allocations,
            isDryRun: $dryRun,
        );
    }
}