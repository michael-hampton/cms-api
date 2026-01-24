<?php

// Repository Tests
namespace App\Tests\Unit\Repositories;

use App\Repositories\Product\MerchantProductFeedRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MerchantProductFeedRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private MerchantProductFeedRepository $repository;

    public function testGetByMerchantReturnsFeeds()
    {
        $merchant1 = $this->createMerchant();
        $merchant2 = $this->createMerchant();

        $this->createMerchantFeed(['merchant_id' => $merchant1->id]);
        $this->createMerchantFeed(['merchant_id' => $merchant1->id]);
        $this->createMerchantFeed(['merchant_id' => $merchant2->id]);

        $feeds = $this->repository->getByMerchant($merchant1->id);

        $this->assertCount(2, $feeds);
    }

    public function testGetActiveFeedsByMerchantReturnsOnlyActive()
    {
        $merchant = $this->createMerchant();

        $this->createMerchantFeed(['merchant_id' => $merchant->id, 'is_active' => true]);
        $this->createMerchantFeed(['merchant_id' => $merchant->id, 'is_active' => false]);
        $this->createMerchantFeed(['merchant_id' => $merchant->id, 'is_active' => true]);

        $feeds = $this->repository->getActiveFeedsByMerchant($merchant->id);

        $this->assertCount(2, $feeds);
        foreach ($feeds as $feed) {
            $this->assertTrue($feed->is_active);
        }
    }

    public function testGetDueForFetchReturnsOverdueFeeds()
    {
        $this->createMerchantFeed([
            'is_active' => true,
            'next_fetch_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))
        ]);

        $this->createMerchantFeed([
            'is_active' => true,
            'next_fetch_at' => date('Y-m-d H:i:s', strtotime('+1 hour'))
        ]);

        $feeds = $this->repository->getDueForFetch();

        $this->assertGreaterThan(0, count($feeds));
    }

    public function testGetByStatusReturnsMatchingFeeds()
    {
        $this->createMerchantFeed(['status' => 'success']);
        $this->createMerchantFeed(['status' => 'failed']);
        $this->createMerchantFeed(['status' => 'success']);

        $feeds = $this->repository->getByStatus('success');

        $this->assertCount(2, $feeds);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new MerchantProductFeedRepository();
    }
}