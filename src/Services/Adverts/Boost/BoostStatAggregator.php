<?php

namespace App\Services\Adverts\Boost;

use App\Contracts\ClockInterface;
use App\Enums\Boost\BoostEventType;
use App\Exceptions\Boost\BoostNotFoundException;
use App\Models\Boost;
use App\Repositories\Adverts\Boost\BoostEventRepository;
use App\Repositories\Adverts\Boost\BoostRepository;
use App\Repositories\Adverts\Boost\BoostStatRepository;
use App\Repositories\Adverts\Boost\MerchantBoostStatRepository;

class BoostStatAggregator
{
    public function __construct(
        private readonly BoostRepository             $boostRepository,
        private readonly BoostEventRepository        $boostEventRepository,
        private readonly BoostStatRepository         $boostStatRepository,
        private readonly MerchantBoostStatRepository $merchantBoostStatRepository,
        private readonly ClockInterface              $clock,
        private readonly BoostScoreCalculator        $scoreCalculator,
    )
    {
    }

    /**
     * Aggregate stats for a single boost and roll up to merchant level.
     * Called by AggregateBoostStatsJob.
     */
    public function aggregate(int $boostId): void
    {
        $boost = $this->boostRepository->find($boostId);

        if (!$boost) {
            throw BoostNotFoundException::forId($boostId);
        }

        $impressions = $this->boostEventRepository->countByType($boostId, BoostEventType::Impression);
        $clicks = $this->boostEventRepository->countByType($boostId, BoostEventType::Click);
        $conversions = $this->boostEventRepository->countByType($boostId, BoostEventType::Conversion);

        $stat = $this->boostStatRepository->upsert($boostId, [
            'boost_id' => $boostId,
            'impressions' => $impressions,
            'clicks' => $clicks,
            'conversions' => $conversions,
            'spend_attributed' => $this->calculateSpendAttributed($boost),
            'last_aggregated_at' => $this->clock->now()->format('Y-m-d H:i:s'),
        ]);

        $boostScore = $this->scoreCalculator->calculate($stat);
        $rankScore = $this->scoreCalculator->rankScore($stat, $boost->multiplier);

        $this->boostStatRepository->upsert($boostId, [
            'boost_score' => $boostScore,
            'rank_score' => $rankScore,
        ]);

        $this->rollUpMerchantStats($boost->merchant_id);
    }

    /**
     * Pro-rate price_paid by the proportion of the boost period that has elapsed.
     * A 7-day boost at £35 attributes £5/day as time passes.
     */
    private function calculateSpendAttributed(Boost $boost): float
    {
        $now = $this->clock->now();
        $startsAt = $boost->starts_at;
        $endsAt = $boost->ends_at;

        // Boost hasn't started yet
        if ($now < $startsAt) {
            return 0.0;
        }

        $totalSeconds = $startsAt->getTimestamp() - $endsAt->getTimestamp();
        $elapsedSeconds = $startsAt->getTimestamp() - min($now, $endsAt)->getTimestamp();

        if ($totalSeconds === 0) {
            return (float)$boost->price_paid;
        }

        $proportion = abs($elapsedSeconds / $totalSeconds);

        return round($boost->price_paid * min($proportion, 1.0), 2);
    }

    private function rollUpMerchantStats(int $merchantId): void
    {
        $totals = $this->boostStatRepository->sumByMerchant($merchantId);

        $this->merchantBoostStatRepository->upsert($merchantId, [
            'merchant_id' => $merchantId,
            'total_impressions' => $totals['impressions'],
            'total_clicks' => $totals['clicks'],
            'total_conversions' => $totals['conversions'],
            'total_spend_attributed' => $totals['spend_attributed'],
            'last_aggregated_at' => $this->clock->now()->format('Y-m-d H:i:s'),
        ]);
    }
}