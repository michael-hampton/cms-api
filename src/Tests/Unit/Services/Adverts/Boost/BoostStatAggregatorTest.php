<?php

namespace App\Tests\Unit\Services\Adverts\Boost;

use App\Enums\Boost\BoostEventType;
use App\Models\Boost;
use App\Models\BoostStat;
use App\Repositories\Adverts\Boost\BoostEventRepository;
use App\Repositories\Adverts\Boost\BoostRepository;
use App\Repositories\Adverts\Boost\BoostStatRepository;
use App\Repositories\Adverts\Boost\MerchantBoostStatRepository;
use App\Services\Adverts\Boost\BoostScoreCalculator;
use App\Services\Adverts\Boost\BoostStatAggregator;
use App\Services\FrozenClock;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class BoostStatAggregatorTest extends FunctionalTestCase
{
    private MockInterface $boostRepository;
    private MockInterface $boostEventRepository;
    private MockInterface $boostStatRepository;
    private MockInterface $merchantBoostStatRepository;
    private MockInterface $scoreCalculator;
    private BoostStatAggregator $aggregator;

    public function test_aggregates_counts_and_scores(): void
    {
        $boost = $this->makeBoost();
        $stat = $this->makeUpsertedStat(100, 10, 2);

        $this->boostRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($boost);

        $this->boostEventRepository
            ->shouldReceive('countByType')
            ->with(1, BoostEventType::Impression)
            ->once()
            ->andReturn(100);

        $this->boostEventRepository
            ->shouldReceive('countByType')
            ->with(1, BoostEventType::Click)
            ->once()
            ->andReturn(10);

        $this->boostEventRepository
            ->shouldReceive('countByType')
            ->with(1, BoostEventType::Conversion)
            ->once()
            ->andReturn(2);

        // First write (metrics)
        $this->boostStatRepository
            ->shouldReceive('upsert')
            ->once()
            ->ordered()
            ->with(1, Mockery::subset([
                'impressions' => 100,
                'clicks' => 10,
                'conversions' => 2,
                'spend_attributed' => 15.0,
            ]))
            ->andReturn($stat);

        $this->scoreCalculator
            ->shouldReceive('calculate')
            ->once()
            ->with($stat)
            ->andReturn(190);

        $this->scoreCalculator
            ->shouldReceive('rankScore')
            ->once()
            ->with($stat, 1.5)
            ->andReturn(285.0);

        // Second write (scores)
        $this->boostStatRepository
            ->shouldReceive('upsert')
            ->once()
            ->ordered()
            ->with(1, [
                'boost_score' => 190,
                'rank_score' => 285.0,
            ])
            ->andReturn($stat);

        $this->boostStatRepository
            ->shouldReceive('sumByMerchant')
            ->once()
            ->with(99)
            ->andReturn([
                'impressions' => 100,
                'clicks' => 10,
                'conversions' => 2,
                'spend_attributed' => 15.0,
            ]);

        $this->merchantBoostStatRepository
            ->shouldReceive('upsert')
            ->once()
            ->with(99, Mockery::subset([
                'total_impressions' => 100,
                'total_clicks' => 10,
                'total_conversions' => 2,
            ]));

        $this->aggregator->aggregate(1);

        $this->addToAssertionCount(1);
    }

    private function makeBoost(): Boost
    {
        $boost = new Boost([
            'merchant_id' => 99,
            'price_paid' => 35.00,
            'multiplier' => 1.5,
            'starts_at' => '2026-01-01 00:00:00',
            'ends_at' => '2026-01-08 00:00:00',
        ]);
        $boost->id = 1;
        return $boost;
    }

    private function makeUpsertedStat(int $impressions = 0, int $clicks = 0, int $conversions = 0): BoostStat
    {
        return new BoostStat([
            'boost_id' => 1,
            'impressions' => $impressions,
            'clicks' => $clicks,
            'conversions' => $conversions,
        ]);
    }

    public function test_pro_rates_spend_by_elapsed_time(): void
    {
        // 2026-01-04 (FrozenClock)
        // Boost: 2026-01-01 → 2026-01-08 (7 days)
        // 3 days elapsed → 3/7 × £35 = £15.00

        $boost = $this->makeBoost();
        $stat = $this->makeUpsertedStat();

        $this->boostRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($boost);

        // No events
        $this->boostEventRepository
            ->shouldReceive('countByType')
            ->andReturn(0);

        // First upsert — we assert spend is correct
        $this->boostStatRepository
            ->shouldReceive('upsert')
            ->once()
            ->ordered()
            ->with(1, Mockery::on(function ($data) {
                return $data['spend_attributed'] === 15.0;
            }))
            ->andReturn($stat);

        $this->scoreCalculator
            ->shouldReceive('calculate')
            ->once()
            ->with($stat)
            ->andReturn(0);

        $this->scoreCalculator
            ->shouldReceive('rankScore')
            ->once()
            ->with($stat, 1.5)
            ->andReturn(0.0);

        // Second upsert (scores)
        $this->boostStatRepository
            ->shouldReceive('upsert')
            ->once()
            ->ordered()
            ->with(1, [
                'boost_score' => 0,
                'rank_score' => 0.0,
            ])
            ->andReturn($stat);

        $this->boostStatRepository
            ->shouldReceive('sumByMerchant')
            ->once()
            ->with(99)
            ->andReturn([
                'impressions' => 0,
                'clicks' => 0,
                'conversions' => 0,
                'spend_attributed' => 15.0,
            ]);

        $this->merchantBoostStatRepository
            ->shouldReceive('upsert')
            ->once();

        $this->aggregator->aggregate(1);

        $this->addToAssertionCount(1);
    }

    public function test_rolls_up_merchant_totals(): void
    {
        $boost = $this->makeBoost();
        $stat = $this->makeUpsertedStat();

        $this->boostRepository->shouldReceive('find')->andReturn($boost);
        $this->boostEventRepository->shouldReceive('countByType')->andReturn(0);

        $this->boostStatRepository->shouldReceive('upsert')->twice()->andReturn($stat);

        $this->scoreCalculator->shouldReceive('calculate')->andReturn(0);
        $this->scoreCalculator->shouldReceive('rankScore')->andReturn(0.0);

        $this->boostStatRepository
            ->shouldReceive('sumByMerchant')
            ->once()
            ->with(99)
            ->andReturn([
                'impressions' => 500,
                'clicks' => 50,
                'conversions' => 5,
                'spend_attributed' => 75.0,
            ]);

        $this->merchantBoostStatRepository
            ->shouldReceive('upsert')
            ->once()
            ->with(99, Mockery::subset([
                'total_impressions' => 500,
                'total_clicks' => 50,
                'total_conversions' => 5,
                'total_spend_attributed' => 75.0,
            ]));

        $this->aggregator->aggregate(1);

        $this->addToAssertionCount(1);
    }

    protected function setUp(): void
    {
        $this->boostRepository = Mockery::mock(BoostRepository::class);
        $this->boostEventRepository = Mockery::mock(BoostEventRepository::class);
        $this->boostStatRepository = Mockery::mock(BoostStatRepository::class);
        $this->merchantBoostStatRepository = Mockery::mock(MerchantBoostStatRepository::class);
        $this->scoreCalculator = Mockery::mock(BoostScoreCalculator::class);

        $clock = new FrozenClock(
            new \DateTimeImmutable('2026-01-04 00:00:00', new \DateTimeZone('UTC'))
        );

        $this->aggregator = new BoostStatAggregator(
            $this->boostRepository,
            $this->boostEventRepository,
            $this->boostStatRepository,
            $this->merchantBoostStatRepository,
            $clock,
            $this->scoreCalculator,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

}