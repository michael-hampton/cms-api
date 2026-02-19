<?php

namespace App\Tests\Unit\Repositories\Adverts\Boost;

use App\Models\Boost;
use App\Models\BoostStat;
use App\Repositories\Adverts\Boost\BoostStatRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class BoostStatRepositoryTest extends FunctionalTestCase
{
    use CreatesTestData;

    private BoostStatRepository $repository;
    private Boost $boost;

    public function test_upsert_creates_stat_when_not_exists(): void
    {
        $stat = $this->repository->upsert($this->boost->id, [
            'boost_id' => $this->boost->id,
            'impressions' => 100,
            'clicks' => 10,
            'conversions' => 2,
            'spend_attributed' => 15.00,
            'last_aggregated_at' => now(),
        ]);

        $this->assertNotNull($stat->id);
        $this->assertEquals(100, $stat->impressions);
    }

    public function test_upsert_updates_existing_stat(): void
    {
        BoostStat::create([
            'boost_id' => $this->boost->id, 'impressions' => 50, 'clicks' => 5,
            'conversions' => 1, 'spend_attributed' => 7.50,
        ]);

        $stat = $this->repository->upsert($this->boost->id, [
            'boost_id' => $this->boost->id,
            'impressions' => 200,
            'clicks' => 20,
        ]);

        $this->assertEquals(200, $stat->impressions);
        $this->assertCount(1, BoostStat::where('boost_id', $this->boost->id)->get());
    }

    public function test_find_by_boost_returns_stat(): void
    {
        BoostStat::create(['boost_id' => $this->boost->id, 'impressions' => 10]);

        $stat = $this->repository->findByBoost($this->boost->id);

        $this->assertNotNull($stat);
        $this->assertEquals($this->boost->id, $stat->boost_id);
    }

    public function test_find_by_boost_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->findByBoost(99999));
    }

    public function test_sum_by_merchant_aggregates_across_boosts(): void
    {
        $boost2 = Boost::create([
            'merchant_id' => 1, 'boostable_type' => 'product', 'boostable_id' => 2,
            'context' => 'deals', 'status' => 'active', 'multiplier' => 1.5,
            'price_paid' => 20.00, 'currency' => 'GBP',
            'starts_at' => '2026-01-01 00:00:00', 'ends_at' => '2026-01-08 00:00:00',
        ]);

        BoostStat::create(['boost_id' => $this->boost->id, 'impressions' => 100, 'clicks' => 10, 'conversions' => 2, 'spend_attributed' => 15.00]);
        BoostStat::create(['boost_id' => $boost2->id, 'impressions' => 200, 'clicks' => 20, 'conversions' => 3, 'spend_attributed' => 10.00]);

        $totals = $this->repository->sumByMerchant(1);

        $this->assertEquals(300, $totals['impressions']);
        $this->assertEquals(30, $totals['clicks']);
        $this->assertEquals(5, $totals['conversions']);
        $this->assertEquals(25.00, $totals['spend_attributed']);
    }

    public function test_sum_by_merchant_returns_zeros_when_no_stats(): void
    {
        $totals = $this->repository->sumByMerchant(99999);

        $this->assertEquals(0, $totals['impressions']);
        $this->assertEquals(0, $totals['clicks']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new BoostStatRepository();
        $this->boost = Boost::create([
            'merchant_id' => 1, 'boostable_type' => 'product', 'boostable_id' => 1,
            'context' => 'listing', 'status' => 'active', 'multiplier' => 1.5,
            'price_paid' => 35.00, 'currency' => 'GBP',
            'starts_at' => '2026-01-01 00:00:00', 'ends_at' => '2026-01-08 00:00:00',
        ]);
    }
}