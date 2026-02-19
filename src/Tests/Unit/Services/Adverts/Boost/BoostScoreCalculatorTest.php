<?php

namespace App\Tests\Unit\Services\Adverts\Boost;

use App\Models\BoostStat;
use App\Services\Adverts\Boost\BoostScoreCalculator;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class BoostScoreCalculatorTest extends FunctionalTestCase
{
    private BoostScoreCalculator $calculator;

    public function test_calculates_score_from_weights(): void
    {
        // (100 * 1) + (10 * 5) + (2 * 20) = 100 + 50 + 40 = 190
        $stat = $this->makeStat(100, 10, 2);
        $score = $this->calculator->calculate($stat);

        $this->assertEquals(190, $score);
    }

    private function makeStat(int $impressions, int $clicks, int $conversions): BoostStat
    {
        return new BoostStat([
            'impressions' => $impressions,
            'clicks' => $clicks,
            'conversions' => $conversions,
        ]);
    }

    public function test_score_is_zero_for_empty_stats(): void
    {
        $stat = $this->makeStat(0, 0, 0);
        $score = $this->calculator->calculate($stat);

        $this->assertEquals(0, $score);
    }

    public function test_rank_score_multiplies_by_boost_multiplier(): void
    {
        // score = 190, multiplier = 1.5 → rank_score = 285.0
        $stat = $this->makeStat(100, 10, 2);
        $rankScore = $this->calculator->rankScore($stat, 1.5);

        $this->assertEquals(285.0, $rankScore);
    }

    public function test_higher_multiplier_produces_higher_rank_score(): void
    {
        $stat = $this->makeStat(50, 5, 1);
        $low = $this->calculator->rankScore($stat, 1.1);
        $high = $this->calculator->rankScore($stat, 2.0);

        $this->assertGreaterThan($low, $high);
    }

    public function test_conversions_outweigh_many_impressions(): void
    {
        $manyImpressions = $this->makeStat(1000, 0, 0); // 1000 points
        $fewConversions = $this->makeStat(0, 0, 51);    // 1020 points

        $this->assertGreaterThan(
            $this->calculator->calculate($manyImpressions),
            $this->calculator->calculate($fewConversions),
        );
    }

    protected function setUp(): void
    {
        // Directly construct with known weights rather than reading config
        // to keep tests deterministic without a config bootstrap.
        $this->calculator = new BoostScoreCalculator();
        parent::setUp();
    }
}