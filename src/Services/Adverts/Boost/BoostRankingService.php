<?php

namespace App\Services\Adverts\Boost;

use App\Framework\Support\Collection;
use App\Framework\Support\Logger;
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

        // Build lookup: [boostable_id => ['boost_id' => X, 'rank' => Y]]
        $rankedLookup = $ranked
            ->pluck('boost')
            ->values()
            ->mapWithKeys(function ($boost, $index) {
                return [
                    $boost->boostable_id => [
                        'boost_id' => $boost->id,
                        'rank' => $index,
                    ]
                ];
            })
            ->toArray();

        $items = $items->map(function ($item) use ($rankedLookup, $idKey) {

            $id = is_array($item)
                ? ($item[$idKey] ?? null)
                : ($item->{$idKey} ?? null);

            $isBoosted = $id !== null && isset($rankedLookup[$id]);

            $boostId = $isBoosted
                ? $rankedLookup[$id]['boost_id']
                : null;

            if (is_array($item)) {
                $item['is_boosted'] = $isBoosted;
                $item['boost_id'] = $boostId;
            } else {
                $item->is_oosted = $isBoosted;
                $item->boost_id = $boostId;
            }

            return $item;
        });

        $boosted = $items->filter(fn($item) => is_array($item)
            ? ($item['is_boosted'] ?? false)
            : ($item->is_boosted ?? false)
        );

        $unboosted = $items->reject(fn($item) => is_array($item)
            ? ($item['is_boosted'] ?? false)
            : ($item->is_boosted ?? false)
        );

        // Sort boosted by rank
        $boosted = $boosted->sortBy(function ($item) use ($rankedLookup, $idKey) {
            $id = is_array($item)
                ? ($item[$idKey] ?? null)
                : ($item->{$idKey} ?? null);

            return $id !== null && isset($rankedLookup[$id])
                ? $rankedLookup[$id]['rank']
                : PHP_INT_MAX;
        })->values();

        return $boosted->merge($unboosted)->values();
    }

    public function getActiveBoostedIds(string $context): array
    {
        try {
            $boosts = $this->boostRepository->getActiveBoostsForContext($context);
            return $boosts->pluck('boostable_id')->toArray();
        } catch (\Exception $e) {
            Logger::error('Failed to fetch boosted IDs', ['error' => $e->getMessage()]);
            return [];
        }
    }
}