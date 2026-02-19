<?php

namespace App\Tests\Unit\Services\Adverts\Boost;

use App\Models\Boost;
use App\Models\BoostStat;
use App\Repositories\Adverts\Boost\BoostRepository;
use App\Repositories\Adverts\Boost\BoostStatRepository;
use App\Services\Adverts\Boost\BoostRankingService;
use App\Services\Adverts\Boost\BoostScoreCalculator;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class BoostRankingServiceTest extends FunctionalTestCase
{
    private MockInterface $boostRepository;
    private MockInterface $boostStatRepository;
    private BoostRankingService $service;

    public function test_returns_boosts_ordered_by_rank_score_descending(): void
    {
        $boostA = $this->makeBoost(1, 1.5); // rank_score = 0 * 1.5 = 0 (no events)
        $boostB = $this->makeBoost(2, 2.0); // rank_score = 190 * 2.0 = 380

        $this->boostRepository->shouldReceive('getActiveBoostsForContext')
            ->with('listing')
            ->andReturn(collect([$boostA, $boostB]));

        $this->boostStatRepository->shouldReceive('findByBoost')->with(1)->andReturn(null);
        $this->boostStatRepository->shouldReceive('findByBoost')->with(2)
            ->andReturn($this->makeStat(100, 10, 2)); // score = 190

        $ranked = $this->service->getRankedBoosts('listing');

        $this->assertEquals(2, $ranked->first()['boost']->id); // B ranks first
        $this->assertEquals(1, $ranked->last()['boost']->id); // A ranks second
    }

    private function makeBoost(int $id, float $multiplier, int $boostableId = 0): Boost
    {
        $boost = new Boost(['multiplier' => $multiplier, 'boostable_id' => $boostableId ?: $id]);
        $boost->id = $id;
        return $boost;
    }

    private function makeStat(int $impressions, int $clicks, int $conversions): BoostStat
    {
        return new BoostStat(['impressions' => $impressions, 'clicks' => $clicks, 'conversions' => $conversions]);
    }

    public function test_falls_back_to_multiplier_when_no_stats(): void
    {
        $boostA = $this->makeBoost(1, 1.5);
        $boostB = $this->makeBoost(2, 3.0);

        $this->boostRepository->shouldReceive('getActiveBoostsForContext')
            ->andReturn(collect([$boostA, $boostB]));

        $this->boostStatRepository->shouldReceive('findByBoost')->andReturn(null);

        $ranked = $this->service->getRankedBoosts('listing');

        // Both have no stats — rank_score = multiplier. B (3.0) > A (1.5)
        $this->assertEquals(2, $ranked->first()['boost']->id);
    }

    public function test_apply_ranking_puts_boosted_items_first(): void
    {
        $boostedProduct = (object)['id' => 10, 'name' => 'Boosted'];
        $unboostedProduct = (object)['id' => 99, 'name' => 'Regular'];

        $boost = $this->makeBoost(1, 1.5, boostableId: 10);

        $this->boostRepository->shouldReceive('getActiveBoostsForContext')
            ->andReturn(collect([$boost]));
        $this->boostStatRepository->shouldReceive('findByBoost')->andReturn(null);

        $collection = collect([$unboostedProduct, $boostedProduct]);
        $result = $this->service->applyRanking($collection, 'listing');

        $this->assertEquals(10, $result->first()->id);
        $this->assertEquals(99, $result->last()->id);
    }

    public function test_returns_empty_collection_when_no_active_boosts(): void
    {
        $this->boostRepository->shouldReceive('getActiveBoostsForContext')
            ->andReturn(collect([]));

        $result = $this->service->getRankedBoosts('listing');

        $this->assertCount(0, $result);
    }

    protected function setUp(): void
    {
        $this->boostRepository = Mockery::mock(BoostRepository::class);
        $this->boostStatRepository = Mockery::mock(BoostStatRepository::class);

        $this->service = new BoostRankingService(
            $this->boostRepository,
            $this->boostStatRepository,
            new BoostScoreCalculator(),
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}