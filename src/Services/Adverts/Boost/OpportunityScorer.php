<?php

namespace App\Services\Adverts\Boost;

use App\Framework\Support\Config;

class OpportunityScorer
{
    private array $thresholds;

    public function __construct()
    {
        $this->thresholds = config('boost.suggestions');
    }

    /**
     * Produces a 0–100 opportunity score for a product candidate.
     *
     * @param string $goal AutoBoostGoal::value or 'default' for equal weights
     */
    public function score(
        string $goal,
        float  $conversionRate,
        float  $averageRating,
        float  $discountPercent,
        int    $stockQuantity,
        int    $impressionsLast30d,
    ): float
    {
        $weights = Config::get("boost.opportunity_weights.{$goal}")
            ?? Config::get('boost.opportunity_weights.maximise_revenue');

        // Normalise each metric to 0–1 range
        $normConversion = min($conversionRate / 10.0, 1.0); // 10% = perfect
        $normRating = min($averageRating / 5.0, 1.0); // 5 stars = perfect
        $normDiscount = min($discountPercent / 60.0, 1.0); // 60% off = perfect
        $normStock = min($stockQuantity / 200.0, 1.0); // 200 units = perfect
        $normLowImp = max(0, 1.0 - ($impressionsLast30d / $this->thresholds['low_impressions_threshold']));

        $score = (
                ($normConversion * $weights['conversion_rate']) +
                ($normRating * $weights['average_rating']) +
                ($normDiscount * $weights['discount_percent']) +
                ($normStock * $weights['stock_level']) +
                ($normLowImp * $weights['low_impressions'])
            ) * 100;

        return round($score, 2);
    }

    /**
     * Derives the boost multiplier from the opportunity score.
     * multiplier = base + (score / 100) * variance, capped at max.
     */
    public function deriveMultiplier(float $opportunityScore): float
    {
        $cfg = Config::get('boost.auto_boost_multiplier');
        $multiplier = $cfg['base'] + ($opportunityScore / 100.0) * $cfg['variance'];
        return round(min($multiplier, $cfg['max']), 2);
    }
}