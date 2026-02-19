<?php

namespace App\Services\Adverts\Boost;

use App\Framework\Support\Collection;
use App\Models\Boost;
use App\Repositories\Adverts\Boost\BoostRepository;
use App\Repositories\Adverts\Boost\BoostStatRepository;

class BoostRankingService
{
    public function __construct(
        private readonly BoostRepository      $boostRepository,
        private readonly BoostStatRepository  $boostStatRepository,
        private readonly BoostScoreCalculator $scoreCalculator,
    )
    {
    }

    /**
     * Returns the boostable IDs in ranked order for a given context.
     * This is what product/search queries consume to re-order their results.
     *
     * Usage:
     *   $rankedIds = $rankingService->getRankedBoostableIds('listing');
     *   // Re-order your collection: boosted items first, then the rest.
     */
    public function getRankedBoostableIds(string $context): array
    {
        return $this->getRankedBoosts($context)
            ->pluck('boost')
            ->map(fn(Boost $b) => $b->boostable_id)
            ->toArray();
    }

    /**
     * Returns active boosts ordered by rank_score descending.
     * rank_score = boost_score * multiplier
     *
     * Used by product/search queries to determine which boosted items
     * surface first within a given context.
     */
    public function getRankedBoosts(string $context): Collection
    {
        $boosts = $this->boostRepository->getActiveBoostsForContext($context);

        return $boosts
            ->map(function (Boost $boost) {
                $stat = $this->boostStatRepository->findByBoost($boost->id);
                $rankScore = $stat
                    ? $this->scoreCalculator->rankScore($stat, $boost->multiplier)
                    : $boost->multiplier; // No stats yet — fall back to multiplier alone

                return [
                    'boost' => $boost,
                    'rank_score' => $rankScore,
                ];
            })
            ->sortByDesc('rank_score')
            ->values();
    }

    /**
     * Given an existing collection of products/offers, re-orders it
     * so that boosted items surface first in rank_score order,
     * followed by non-boosted items in their original order.
     *
     * Usage in a product repository:
     *   return $rankingService->applyRanking($products, 'listing');
     */
    public function applyRanking(Collection $items, string $context, string $idKey = 'id'): Collection
    {
        $ranked = $this->getRankedBoosts($context);

        $rankedIds = $ranked->pluck('boost')->map(fn($b) => $b->boostable_id)->flip()->toArray();

        $boosted = $items->filter(function ($item) use ($rankedIds, $idKey) {
            $id = is_array($item) ? ($item[$idKey] ?? null) : ($item->{$idKey} ?? null);
            return $id !== null && isset($rankedIds[$id]);
        });

        $unboosted = $items->filter(function ($item) use ($rankedIds, $idKey) {
            $id = is_array($item) ? ($item[$idKey] ?? null) : ($item->{$idKey} ?? null);
            return $id === null || !isset($rankedIds[$id]);
        });

        // Sort boosted subset by their rank position
        $boosted = $boosted->sortBy(function ($item) use ($rankedIds, $idKey) {
            $id = is_array($item) ? ($item[$idKey] ?? null) : ($item->{$idKey} ?? null);
            return $id !== null ? $rankedIds[$id] : PHP_INT_MAX;
        })->values();

        return $boosted->merge($unboosted)->values();
    }
}