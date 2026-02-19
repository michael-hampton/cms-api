<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Score Weights
    |--------------------------------------------------------------------------
    | Points awarded per event type when calculating a boost's success score.
    | score = (impressions * weight) + (clicks * weight) + (conversions * weight)
    | Final rank_score = boost_score * multiplier
    */
    'score_weights' => [
        'impression' => 1,
        'click' => 5,
        'conversion' => 20,
    ],

    /*
|--------------------------------------------------------------------------
| Suggestion Engine
|--------------------------------------------------------------------------
*/
    'suggestions' => [
        'max_results' => 5,
        'low_impressions_threshold' => 500,   // Below this = "low visibility"
        'high_conversion_threshold' => 2.0,   // % — above this = "converts well"
        'high_rating_threshold' => 4.5,
        'high_stock_threshold' => 50,    // Units — above this considered "good stock"
        'slow_mover_days_threshold' => 180,   // Stock covers 180+ days = slow mover
        'strong_deal_discount_min' => 20.0,  // % — minimum discount to qualify
        'boost_expiry_warning_days' => 3,     // "Expiring soon" window
        'analysis_window_days' => 30,
    ],

    /*
|--------------------------------------------------------------------------
| Opportunity Scoring Weights (per goal)
|--------------------------------------------------------------------------
| Weights applied when computing opportunity score.
| Score = Σ (weight × normalised_metric)
*/
    'opportunity_weights' => [
        'maximise_revenue' => [
            'conversion_rate' => 0.40,
            'average_rating' => 0.25,
            'discount_percent' => 0.10,
            'stock_level' => 0.10,
            'low_impressions' => 0.15,   // Inverted — lower impressions = higher score
        ],
        'promote_deals' => [
            'conversion_rate' => 0.15,
            'average_rating' => 0.10,
            'discount_percent' => 0.45,
            'stock_level' => 0.10,
            'low_impressions' => 0.20,
        ],
        'clear_inventory' => [
            'conversion_rate' => 0.10,
            'average_rating' => 0.10,
            'discount_percent' => 0.15,
            'stock_level' => 0.45,   // High stock = high urgency
            'low_impressions' => 0.20,
        ],
    ],

    /*
|--------------------------------------------------------------------------
| Auto Boost Multiplier Derivation
|--------------------------------------------------------------------------
| multiplier = base + (opportunity_score / 100) * variance
| Capped at max.
*/
    'auto_boost_multiplier' => [
        'base' => 1.2,
        'variance' => 0.8,
        'max' => 2.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto Boost Durations (days per context)
    |--------------------------------------------------------------------------
    */
    'auto_boost_durations' => [
        'listing' => 7,
        'deals' => 3,
        'recommendations' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Attribution Windows
    |--------------------------------------------------------------------------
    | How long after an event a conversion can be attributed to a boost.
    | Values are in hours.
    */
    'attribution_windows' => [
        'click' => 24,   // 24 hours
        'impression' => 168,  // 7 days
    ],

    /*
    |--------------------------------------------------------------------------
    | Aggregation
    |--------------------------------------------------------------------------
    | How frequently AggregateBoostStatsJob runs (informational — set in
    | your scheduler). soft_enforcement_note is shown in the UI and emails
    | when a boost is paused by limit breach.
    */
    'aggregation_interval_minutes' => 5,

    'soft_enforcement_note' => 'Boost limits are checked every 5 minutes. '
        . 'Your boost may slightly exceed its limit before being paused.',

    /*
    |--------------------------------------------------------------------------
    | Pricing — Base Rates Per Day (£)
    |--------------------------------------------------------------------------
    */
    'base_rates' => [
        'listing' => 5.00,
        'deals' => 8.00,
        'recommendations' => 3.00,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pricing — Type Multipliers
    |--------------------------------------------------------------------------
    */
    'type_multipliers' => [
        'product' => 1.0,
        'offer' => 1.2,
    ],

];