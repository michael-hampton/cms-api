<?php

namespace App\Tests\Unit\Services;

use App\Models\MerchantProductFeed;
use App\Repositories\Product\MerchantProductFeedRepository;
use App\Services\Product\MerchantProductFeedService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class MerchantProductFeedServiceTest extends FunctionalTestCase
{
    protected $repository;
    protected MerchantProductFeedService $service;

    public function testCreateFeedSetsDefaults()
    {
        $data = [
            'merchant_id' => 1,
            'feed_url' => 'https://example.com/feed.xml',
            'feed_type' => 'xml',
            'fetch_frequency' => 'daily'
        ];

        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return isset($arg['status']) &&
                    isset($arg['is_active']) &&
                    isset($arg['next_fetch_at']);
            }))
            ->andReturn(new MerchantProductFeed($data));

        $result = $this->service->createFeed($data);

        $this->assertNotNull($result);
    }

    public function testUpdateFeedRecalculatesNextFetchTime()
    {
        $feed = new MerchantProductFeed([
            'id' => 1,
            'fetch_frequency' => 'daily'
        ]);

        $this->repository->shouldReceive('find')
            ->with(1)
            ->andReturn($feed);

        $this->repository->shouldReceive('update')
            ->once()
            ->with(1, Mockery::on(function ($arg) {
                return isset($arg['next_fetch_at']);
            }))
            ->andReturn($feed);

        $result = $this->service->updateFeed(1, ['fetch_frequency' => 'hourly']);

        $this->assertNotNull($result);
    }

    public function testFetchFeedUpdatesStatus()
    {
        $feed = new MerchantProductFeed([
            'id' => 1,
            'merchant_id' => 1,
            'feed_url' => 'https://example.com/feed.xml',
            'fetch_frequency' => 'daily',
            'status' => 'pending'
        ]);

        $this->repository->shouldReceive('find')
            ->with(1)
            ->andReturn($feed);

        $this->repository->shouldReceive('update')
            ->with(1, Mockery::on(function ($arg) {
                return $arg['status'] === 'processing';
            }))
            ->once();

        $this->repository->shouldReceive('update')
            ->with(1, Mockery::on(function ($arg) {
                return $arg['status'] === 'success' &&
                    isset($arg['last_fetched_at']) &&
                    isset($arg['next_fetch_at']);
            }))
            ->once()
            ->andReturn($feed);

        $result = $this->service->fetchFeed(1);

        $this->assertNotNull($result);
    }

    public function testDownloadFeedDataReturnsCorrectFormat()
    {
        $xmlFeed = new MerchantProductFeed([
            'id' => 1,
            'feed_type' => 'xml'
        ]);

        $this->repository->shouldReceive('find')
            ->with(1)
            ->andReturn($xmlFeed);

        $result = $this->service->downloadFeedData(1);

        $this->assertStringContainsString('<?xml', $result);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = Mockery::mock(MerchantProductFeedRepository::class);
        $this->service = new MerchantProductFeedService($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}