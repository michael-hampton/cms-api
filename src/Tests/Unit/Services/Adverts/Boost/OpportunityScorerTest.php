<?php

namespace App\Tests\Unit\Services\Adverts\Boost;

use App\Framework\Support\Config;
use App\Services\Adverts\Boost\OpportunityScorer;
use PHPUnit\Framework\TestCase;

class OpportunityScorerTest extends TestCase
{
    private OpportunityScorer $scorer;

    public function test_high_conversion_low_impressions_scores_high_for_revenue_goal(): void
    {
        $score = $this->scorer->score(
            goal: 'maximise_revenue',
            conversionRate: 8.0,
            averageRating: 4.8,
            discountPercent: 0.0,
            stockQuantity: 100,
            impressionsLast30d: 50,   // very low
        );

        $this->assertGreaterThan(60, $score);
    }

    public function test_high_discount_scores_highest_for_promote_deals_goal(): void
    {
        $deals = $this->scorer->score(
            goal: 'promote_deals',
            conversionRate: 2.0,
            averageRating: 4.0,
            discountPercent: 50.0,  // large discount
            stockQuantity: 80,
            impressionsLast30d: 300,
        );

        $revenue = $this->scorer->score(
            goal: 'maximise_revenue',
            conversionRate: 2.0,
            averageRating: 4.0,
            discountPercent: 50.0,
            stockQuantity: 80,
            impressionsLast30d: 300,
        );

        $this->assertGreaterThan($revenue, $deals);
    }

    public function test_high_stock_low_velocity_scores_highest_for_clear_inventory(): void
    {
        $clearInventory = $this->scorer->score(
            goal: 'clear_inventory',
            conversionRate: 0.1,
            averageRating: 3.5,
            discountPercent: 10.0,
            stockQuantity: 500,   // massive stock
            impressionsLast30d: 100,
        );

        $revenue = $this->scorer->score(
            goal: 'maximise_revenue',
            conversionRate: 0.1,
            averageRating: 3.5,
            discountPercent: 10.0,
            stockQuantity: 500,
            impressionsLast30d: 100,
        );

        $this->assertGreaterThan($revenue, $clearInventory);
    }

    public function test_score_is_between_zero_and_one_hundred(): void
    {
        $score = $this->scorer->score('maximise_revenue', 10, 5, 60, 200, 0);
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    public function test_score_is_zero_for_all_zero_inputs(): void
    {
        $score = $this->scorer->score('maximise_revenue', 0, 0, 0, 0, 500);
        $this->assertEquals(0, $score);
    }

    public function test_derive_multiplier_increases_with_score(): void
    {
        $low = $this->scorer->deriveMultiplier(10);
        $high = $this->scorer->deriveMultiplier(90);

        $this->assertGreaterThan($low, $high);
    }

    public function test_derive_multiplier_is_capped_at_max(): void
    {
        $multiplier = $this->scorer->deriveMultiplier(100);
        $this->assertLessThanOrEqual(2.0, $multiplier);
    }

    public function test_derive_multiplier_never_below_base(): void
    {
        $multiplier = $this->scorer->deriveMultiplier(0);
        $this->assertGreaterThanOrEqual(1.2, $multiplier);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // OpportunityScorer reads Framework Config (not the file-backed config() helper).
        Config::set('boost', require dirname(__DIR__, 5) . '/config/boost.php');

        $this->scorer = new OpportunityScorer();
    }
}
