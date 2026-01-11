<?php

namespace App\Tests\Unit\Services;

use App\Models\Member;
use App\Repositories\Recommendations\ContentRecommendationRepository;
use App\Repositories\Recommendations\TrendingContentRepository;
use App\Services\Recommendations\ContentRecommendationService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class ContentRecommendationServiceTest extends FunctionalTestCase
{
    private $recommendationRepository;
    private $trendingRepository;
    private $service;

    public function testGetRecommendedForMemberUpdatesPreferencesAndReturnsRecommendations(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $siteId = 1;
        $limit = 6;
        $expectedCollection = collect([]);

        $this->recommendationRepository
            ->shouldReceive('updatePreferencesFromActivity')
            ->once()
            ->with($member->id, $siteId);

        $this->recommendationRepository
            ->shouldReceive('getRecommendedPages')
            ->once()
            ->with($member->id, $siteId, $limit)
            ->andReturn($expectedCollection);

        $result = $this->service->getRecommendedForMember($member, $siteId, $limit);

        $this->assertSame($expectedCollection, $result);
    }

    public function testGetRecommendedForMemberUsesDefaultLimit(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $siteId = 1;
        $expectedCollection = collect([]);

        $this->recommendationRepository
            ->shouldReceive('updatePreferencesFromActivity')
            ->once()
            ->with($member->id, $siteId);

        $this->recommendationRepository
            ->shouldReceive('getRecommendedPages')
            ->once()
            ->with($member->id, $siteId, 6)
            ->andReturn($expectedCollection);

        $result = $this->service->getRecommendedForMember($member, $siteId);

        $this->assertSame($expectedCollection, $result);
    }

    public function testGetTrendingContentCallsRepository(): void
    {
        $siteId = 1;
        $limit = 10;
        $expectedCollection = collect([]);

        $this->trendingRepository
            ->shouldReceive('getTrendingPages')
            ->once()
            ->with($siteId, $limit)
            ->andReturn($expectedCollection);

        $result = $this->service->getTrendingContent($siteId, $limit);

        $this->assertSame($expectedCollection, $result);
    }

    public function testGetTrendingContentUsesDefaultLimit(): void
    {
        $siteId = 1;
        $expectedCollection = collect([]);

        $this->trendingRepository
            ->shouldReceive('getTrendingPages')
            ->once()
            ->with($siteId, 6)
            ->andReturn($expectedCollection);

        $result = $this->service->getTrendingContent($siteId);

        $this->assertSame($expectedCollection, $result);
    }

    public function testGetTrendingConversationsCallsRepository(): void
    {
        $siteId = 1;
        $limit = 8;
        $expectedCollection = collect([]);

        $this->trendingRepository
            ->shouldReceive('getTrendingConversations')
            ->once()
            ->with($siteId, $limit)
            ->andReturn($expectedCollection);

        $result = $this->service->getTrendingConversations($siteId, $limit);

        $this->assertSame($expectedCollection, $result);
    }

    public function testGetTrendingConversationsUsesDefaultLimit(): void
    {
        $siteId = 1;
        $expectedCollection = collect([]);

        $this->trendingRepository
            ->shouldReceive('getTrendingConversations')
            ->once()
            ->with($siteId, 6)
            ->andReturn($expectedCollection);

        $result = $this->service->getTrendingConversations($siteId);

        $this->assertSame($expectedCollection, $result);
    }

    public function testUpdateTrendingScoresCallsRepository(): void
    {
        $siteId = 1;

        $this->trendingRepository
            ->shouldReceive('calculateTrendingScores')
            ->once()
            ->with($siteId);

        $this->service->updateTrendingScores($siteId);

        // No assertion needed - we're verifying the method was called
        $this->assertTrue(true);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->recommendationRepository = Mockery::mock(ContentRecommendationRepository::class);
        $this->trendingRepository = Mockery::mock(TrendingContentRepository::class);
        $this->service = new ContentRecommendationService(
            $this->recommendationRepository,
            $this->trendingRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}