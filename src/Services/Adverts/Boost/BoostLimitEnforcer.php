<?php

namespace App\Services\Adverts\Boost;

use App\Events\Boost\BoostLimitBreachedEvent;
use App\Models\BoostStat;
use App\Repositories\Adverts\Boost\BoostRepository;
use App\Repositories\Adverts\Boost\BoostStatRepository;

class BoostLimitEnforcer
{
    public function __construct(
        private readonly BoostRepository     $boostRepository,
        private readonly BoostStatRepository $boostStatRepository,
        private readonly BoostService        $boostService,
    )
    {
    }

    /**
     * Check a boost's current stats against its limits.
     * If breached and pause_on_breach is true, pauses the boost and emits an event.
     * Called by EnforceBoostLimitsJob after each aggregation cycle.
     */
    public function enforce(int $boostId): void
    {
        $boost = $this->boostRepository->find($boostId);

        if (!$boost || !$boost->isActive()) {
            return;
        }

        $limit = $boost->limit;

        if (!$limit) {
            return;
        }

        $stat = $this->boostStatRepository->findByBoost($boostId);

        if (!$stat) {
            return;
        }

        $breach = $this->detectBreach($limit, $stat);

        if ($breach === null) {
            return;
        }

        [$limitType, $limitValue, $currentValue] = $breach;

        if ($limit->pause_on_breach) {
            $this->boostService->pauseBoost($boostId);
        }

        event(new BoostLimitBreachedEvent(
            boost: $boost,
            limitType: $limitType,
            limitValue: $limitValue,
            currentValue: $currentValue,
        ));
    }

    /**
     * Returns [limitType, limitValue, currentValue] or null if no breach.
     * Checks spend first (most impactful), then clicks, then impressions.
     */
    private function detectBreach(
        \App\Models\BoostLimit $limit,
        BoostStat              $stat
    ): ?array
    {
        if ($limit->hasSpendLimit() && $stat->spend_attributed >= $limit->max_spend) {
            return ['spend', $limit->max_spend, $stat->spend_attributed];
        }

        if ($limit->hasClickLimit() && $stat->clicks >= $limit->max_clicks) {
            return ['clicks', $limit->max_clicks, $stat->clicks];
        }

        if ($limit->hasImpressionLimit() && $stat->impressions >= $limit->max_impressions) {
            return ['impressions', $limit->max_impressions, $stat->impressions];
        }

        return null;
    }
}