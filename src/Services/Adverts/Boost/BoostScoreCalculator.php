<?php

namespace App\Services\Adverts\Boost;

use App\Framework\Support\Config;
use App\Models\BoostStat;

class BoostScoreCalculator
{
    private int $impressionWeight;
    private int $clickWeight;
    private int $conversionWeight;

    public function __construct()
    {
        $weights = Config::get('boost.score_weights');

        $this->impressionWeight = (int)$weights['impression'];
        $this->clickWeight = (int)$weights['click'];
        $this->conversionWeight = (int)$weights['conversion'];
    }

    /**
     * Rank score used for ordering competing boosts.
     * rank_score = boost_score * multiplier
     * Higher rank_score wins the context slot.
     */
    public function rankScore(BoostStat $stat, float $multiplier): float
    {
        return $this->calculate($stat) * $multiplier;
    }

    /**
     * Raw success score from event stats.
     * score = (impressions * 1) + (clicks * 5) + (conversions * 20)
     */
    public function calculate(BoostStat $stat): int
    {
        return ($stat->impressions * $this->impressionWeight)
            + ($stat->clicks * $this->clickWeight)
            + ($stat->conversions * $this->conversionWeight);
    }
}