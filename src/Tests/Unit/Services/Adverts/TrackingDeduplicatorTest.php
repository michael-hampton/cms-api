<?php

namespace App\Tests\Unit\Services\Adverts;

use App\Repositories\Offers\DealClickRepository;
use App\Repositories\Offers\ProductOfferRepository;
use App\Repositories\Rewards\RewardsRepository;
use App\Services\Adverts\TrackingDeduplicator;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class TrackingDeduplicatorTest extends TestCase
{
    private MockInterface $offerRepository;
    private MockInterface $dealClickRepository;
    private MockInterface $rewardsRepository;

    private TrackingDeduplicator $deduplicator;

    public function testReturnsTrueWhenOfferAlreadyTracked(): void
    {
        $this->offerRepository
            ->expects('hasTracked')
            ->with(42, 10, 'render', 'page', 1)
            ->andReturn(true);

        $this->assertTrue(
            $this->deduplicator->alreadyTrackedOffer(42, 10, 'render', 'page', 1)
        );
    }

    public function testReturnsFalseWhenOfferNotYetTracked(): void
    {
        $this->offerRepository
            ->expects('hasTracked')
            ->with(42, 10, 'click', 'page', 1)
            ->andReturn(false);

        $this->assertFalse(
            $this->deduplicator->alreadyTrackedOffer(42, 10, 'click', 'page', 1)
        );
    }

    // -------------------------------------------------------------------------
    // Offer
    // -------------------------------------------------------------------------

    public function testOfferDeduplicatesRenderAndClickIndependently(): void
    {
        // Render already tracked
        $this->offerRepository
            ->expects('hasTracked')
            ->with(42, 10, 'render', 'page', 1)
            ->andReturn(true);

        // Click not yet tracked for the same entity+member+surface
        $this->offerRepository
            ->expects('hasTracked')
            ->with(42, 10, 'click', 'page', 1)
            ->andReturn(false);

        $this->assertTrue(
            $this->deduplicator->alreadyTrackedOffer(42, 10, 'render', 'page', 1)
        );
        $this->assertFalse(
            $this->deduplicator->alreadyTrackedOffer(42, 10, 'click', 'page', 1)
        );
    }

    public function testOfferDeduplicatesPerSurfaceIndependently(): void
    {
        // Same offer+member+action but different surfaces — both must be checked separately
        $this->offerRepository
            ->expects('hasTracked')
            ->with(42, 10, 'render', 'page', 1)
            ->andReturn(true);

        $this->offerRepository
            ->expects('hasTracked')
            ->with(42, 10, 'render', 'page', 2)
            ->andReturn(false);

        $this->assertTrue(
            $this->deduplicator->alreadyTrackedOffer(42, 10, 'render', 'page', 1)
        );
        $this->assertFalse(
            $this->deduplicator->alreadyTrackedOffer(42, 10, 'render', 'page', 2)
        );
    }

    public function testReturnsTrueWhenDealAlreadyTracked(): void
    {
        $this->dealClickRepository
            ->expects('hasTracked')
            ->with(99, 7, 'render', 'page', 5)
            ->andReturn(true);

        $this->assertTrue(
            $this->deduplicator->alreadyTrackedDeal(99, 7, 'render', 'page', 5)
        );
    }

    public function testReturnsFalseWhenDealNotYetTracked(): void
    {
        $this->dealClickRepository
            ->expects('hasTracked')
            ->with(99, 7, 'click', 'page', 5)
            ->andReturn(false);

        $this->assertFalse(
            $this->deduplicator->alreadyTrackedDeal(99, 7, 'click', 'page', 5)
        );
    }

    // -------------------------------------------------------------------------
    // Deal
    // -------------------------------------------------------------------------

    public function testDealDeduplicatesRenderAndClickIndependently(): void
    {
        $this->dealClickRepository
            ->expects('hasTracked')
            ->with(99, 7, 'render', 'page', 5)
            ->andReturn(true);

        $this->dealClickRepository
            ->expects('hasTracked')
            ->with(99, 7, 'click', 'page', 5)
            ->andReturn(false);

        $this->assertTrue(
            $this->deduplicator->alreadyTrackedDeal(99, 7, 'render', 'page', 5)
        );
        $this->assertFalse(
            $this->deduplicator->alreadyTrackedDeal(99, 7, 'click', 'page', 5)
        );
    }

    public function testDealDeduplicatesPerSurfaceIndependently(): void
    {
        $this->dealClickRepository
            ->expects('hasTracked')
            ->with(99, 7, 'render', 'page', 5)
            ->andReturn(true);

        $this->dealClickRepository
            ->expects('hasTracked')
            ->with(99, 7, 'render', 'page', 6)
            ->andReturn(false);

        $this->assertTrue(
            $this->deduplicator->alreadyTrackedDeal(99, 7, 'render', 'page', 5)
        );
        $this->assertFalse(
            $this->deduplicator->alreadyTrackedDeal(99, 7, 'render', 'page', 6)
        );
    }

    public function testReturnsTrueWhenRewardAlreadyTracked(): void
    {
        $this->rewardsRepository
            ->expects('hasTracked')
            ->with(55, 3, 'render', 'page', 8)
            ->andReturn(true);

        $this->assertTrue(
            $this->deduplicator->alreadyTrackedReward(55, 3, 'render', 'page', 8)
        );
    }

    public function testReturnsFalseWhenRewardNotYetTracked(): void
    {
        $this->rewardsRepository
            ->expects('hasTracked')
            ->with(55, 3, 'click', 'page', 8)
            ->andReturn(false);

        $this->assertFalse(
            $this->deduplicator->alreadyTrackedReward(55, 3, 'click', 'page', 8)
        );
    }

    // -------------------------------------------------------------------------
    // Reward
    // -------------------------------------------------------------------------

    public function testRewardDeduplicatesRenderAndClickIndependently(): void
    {
        $this->rewardsRepository
            ->expects('hasTracked')
            ->with(55, 3, 'render', 'page', 8)
            ->andReturn(true);

        $this->rewardsRepository
            ->expects('hasTracked')
            ->with(55, 3, 'click', 'page', 8)
            ->andReturn(false);

        $this->assertTrue(
            $this->deduplicator->alreadyTrackedReward(55, 3, 'render', 'page', 8)
        );
        $this->assertFalse(
            $this->deduplicator->alreadyTrackedReward(55, 3, 'click', 'page', 8)
        );
    }

    public function testRewardDeduplicatesPerSurfaceIndependently(): void
    {
        $this->rewardsRepository
            ->expects('hasTracked')
            ->with(55, 3, 'render', 'page', 8)
            ->andReturn(true);

        $this->rewardsRepository
            ->expects('hasTracked')
            ->with(55, 3, 'render', 'page', 9)
            ->andReturn(false);

        $this->assertTrue(
            $this->deduplicator->alreadyTrackedReward(55, 3, 'render', 'page', 8)
        );
        $this->assertFalse(
            $this->deduplicator->alreadyTrackedReward(55, 3, 'render', 'page', 9)
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->offerRepository = Mockery::mock(ProductOfferRepository::class);
        $this->dealClickRepository = Mockery::mock(DealClickRepository::class);
        $this->rewardsRepository = Mockery::mock(RewardsRepository::class);

        $this->deduplicator = new TrackingDeduplicator(
            offerRepository: $this->offerRepository,
            dealClickRepository: $this->dealClickRepository,
            rewardsRepository: $this->rewardsRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}