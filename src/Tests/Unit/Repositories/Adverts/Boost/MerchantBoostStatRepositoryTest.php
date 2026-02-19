<?php

namespace App\Tests\Unit\Repositories\Adverts\Boost;

use App\Models\MerchantBoostStat;
use App\Repositories\Adverts\Boost\MerchantBoostStatRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MerchantBoostStatRepositoryTest extends FunctionalTestCase
{
    use CreatesTestData;

    private MerchantBoostStatRepository $repository;

    public function test_upsert_creates_when_not_exists(): void
    {
        $stat = $this->repository->upsert(1, [
            'merchant_id' => 1,
            'total_impressions' => 500,
            'total_clicks' => 50,
            'total_conversions' => 5,
            'total_spend_attributed' => 75.00,
            'last_aggregated_at' => now(),
        ]);

        $this->assertNotNull($stat->id);
        $this->assertEquals(500, $stat->total_impressions);
    }

    public function test_upsert_updates_when_exists(): void
    {
        MerchantBoostStat::create([
            'merchant_id' => 1, 'total_impressions' => 100,
            'total_clicks' => 10, 'total_conversions' => 1, 'total_spend_attributed' => 15.00,
        ]);

        $stat = $this->repository->upsert(1, [
            'merchant_id' => 1,
            'total_impressions' => 999,
        ]);

        $this->assertEquals(999, $stat->total_impressions);
        $this->assertCount(1, MerchantBoostStat::where('merchant_id', 1)->get());
    }

    public function test_find_by_merchant_returns_stat(): void
    {
        MerchantBoostStat::create([
            'merchant_id' => 1, 'total_impressions' => 200,
            'total_clicks' => 20, 'total_conversions' => 2, 'total_spend_attributed' => 30.00,
        ]);

        $stat = $this->repository->findByMerchant(1);

        $this->assertNotNull($stat);
        $this->assertEquals(200, $stat->total_impressions);
    }

    public function test_find_by_merchant_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->findByMerchant(99999));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new MerchantBoostStatRepository();
    }
}