<?php

namespace App\Tests\Unit\Services\Adverts;

use App\Framework\Database\Database;
use App\Models\MemberReward;
use App\Repositories\Offers\DealClickRepository;
use App\Repositories\Offers\ProductOfferRepository;
use App\Repositories\Rewards\RewardsRepository;
use App\Services\Adverts\DealTrackingRecorder;
use App\Services\Adverts\RenderContext;
use App\Services\Adverts\TrackingDeduplicator;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class DealTrackingRecorderTest extends TestCase
{
    private MockInterface $offerRepository;
    private MockInterface $rewardsRepository;
    private MockInterface $dealClickRepository;
    private MockInterface $deduplicator;
    private MockInterface $database;

    private DealTrackingRecorder $recorder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->offerRepository = Mockery::mock(ProductOfferRepository::class);
        $this->rewardsRepository = Mockery::mock(RewardsRepository::class);
        $this->dealClickRepository = Mockery::mock(DealClickRepository::class);
        $this->deduplicator = Mockery::mock(TrackingDeduplicator::class);
        $this->database = Mockery::mock(Database::class);

        // Execute transactions inline so tested logic runs without real DB
        $this->database
            ->allows('transaction')
            ->andReturnUsing(fn(callable $cb) => $cb());

        $this->recorder = new DealTrackingRecorder(
            offerRepository: $this->offerRepository,
            rewardsRepository: $this->rewardsRepository,
            dealClickRepository: $this->dealClickRepository,
            deduplicator: $this->deduplicator,
            database: $this->database,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Offer render
    // -------------------------------------------------------------------------

    public function testRecordsOfferRenderWhenNotDuplicate(): void
    {
        $context = $this->makeContext(memberId: 10, surfaceType: 'page', surfaceId: 1);

        $this->deduplicator
            ->expects('alreadyTrackedOffer')
            ->with(42, 10, 'render', 'page', 1)
            ->andReturn(false);

        $this->offerRepository
            ->expects('trackClick')
            ->with(42, 10, 'render', '127.0.0.1', 'TestAgent', Mockery::any())
            ->once();

        $this->recorder->recordOfferRender(42, null, $context, '127.0.0.1', 'TestAgent');
        $this->assertTrue(true);
    }

    public function testSkipsOfferRenderWhenDuplicate(): void
    {
        $context = $this->makeContext(memberId: 10, surfaceType: 'page', surfaceId: 1);

        $this->deduplicator
            ->expects('alreadyTrackedOffer')
            ->andReturn(true);

        $this->offerRepository
            ->shouldNotReceive('trackClick');

        $this->recorder->recordOfferRender(42, null, $context);
        $this->assertTrue(true);
    }

    public function testOfferRenderSkipsDeduplicatorForGuest(): void
    {
        $context = $this->makeContext(memberId: null, surfaceType: 'page', surfaceId: 1);

        $this->deduplicator->shouldNotReceive('alreadyTrackedOffer');

        $this->offerRepository
            ->expects('trackClick')
            ->once();

        $this->recorder->recordOfferRender(42, null, $context);
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Offer click
    // -------------------------------------------------------------------------

    public function testRecordsOfferClickWhenNotDuplicate(): void
    {
        $context = $this->makeContext(memberId: 10, surfaceType: 'page', surfaceId: 1);

        $this->deduplicator
            ->expects('alreadyTrackedOffer')
            ->with(42, 10, 'click', 'page', 1)
            ->andReturn(false);

        $this->offerRepository
            ->expects('trackClick')
            ->with(42, 10, 'click', '', '', Mockery::any())
            ->once();

        $this->recorder->recordOfferClick(42, null, $context);
        $this->assertTrue(true);
    }

    public function testSkipsOfferClickWhenDuplicate(): void
    {
        $context = $this->makeContext(memberId: 10, surfaceType: 'page', surfaceId: 1);

        $this->deduplicator
            ->expects('alreadyTrackedOffer')
            ->andReturn(true);

        $this->offerRepository->shouldNotReceive('trackClick');

        $this->recorder->recordOfferClick(42, null, $context);
        $this->assertTrue(true);
    }

    public function testOfferClickSkipsDeduplicatorForGuest(): void
    {
        $context = $this->makeContext(memberId: null, surfaceType: 'page', surfaceId: 1);

        $this->deduplicator->shouldNotReceive('alreadyTrackedOffer');

        $this->offerRepository
            ->expects('trackClick')
            ->once();

        $this->recorder->recordOfferClick(42, null, $context);
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Deal render
    // -------------------------------------------------------------------------

    public function testRecordsDealRenderWhenNotDuplicate(): void
    {
        $context = $this->makeContext(memberId: 7, surfaceType: 'page', surfaceId: 5);

        $this->deduplicator
            ->expects('alreadyTrackedDeal')
            ->with(99, 7, 'render', 'page', 5)
            ->andReturn(false);

        $this->dealClickRepository
            ->expects('trackClick')
            ->once();

        $this->recorder->recordDealRender(99, $context, '', '', 1);
        $this->assertTrue(true);
    }

    public function testSkipsDealRenderWhenDuplicate(): void
    {
        $context = $this->makeContext(memberId: 7, surfaceType: 'page', surfaceId: 5);

        $this->deduplicator
            ->expects('alreadyTrackedDeal')
            ->andReturn(true);

        $this->dealClickRepository->shouldNotReceive('trackClick');

        $this->recorder->recordDealRender(99, $context);
        $this->assertTrue(true);
    }

    public function testDealRenderSkipsDeduplicatorForGuest(): void
    {
        $context = $this->makeContext(memberId: null, surfaceType: 'page', surfaceId: 5);

        $this->deduplicator->shouldNotReceive('alreadyTrackedDeal');

        $this->dealClickRepository
            ->expects('trackClick')
            ->once();

        $this->recorder->recordDealRender(99, $context, '', '', 1);
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Deal click
    // -------------------------------------------------------------------------

    public function testRecordsDealClickWhenNotDuplicate(): void
    {
        $context = $this->makeContext(memberId: 7, surfaceType: 'page', surfaceId: 5);

        $this->deduplicator
            ->expects('alreadyTrackedDeal')
            ->with(99, 7, 'click', 'page', 5)
            ->andReturn(false);

        $this->dealClickRepository
            ->expects('trackClick')
            ->once();

        $this->recorder->recordDealClick(99, $context, '', '', 1);
        $this->assertTrue(true);
    }

    public function testSkipsDealClickWhenDuplicate(): void
    {
        $context = $this->makeContext(memberId: 7, surfaceType: 'page', surfaceId: 5);

        $this->deduplicator
            ->expects('alreadyTrackedDeal')
            ->andReturn(true);

        $this->dealClickRepository->shouldNotReceive('trackClick');

        $this->recorder->recordDealClick(99, $context);
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Reward render
    // -------------------------------------------------------------------------

    public function testRecordsRewardRenderWhenNotDuplicate(): void
    {
        $context = $this->makeContext(memberId: 3, surfaceType: 'page', surfaceId: 8);

        $this->deduplicator
            ->expects('alreadyTrackedReward')
            ->with(55, 3, 'render', 'page', 8)
            ->andReturn(false);

        $this->rewardsRepository
            ->expects('trackClick')
            ->once();

        $this->recorder->recordRewardRender(55, null, $context, '', '', 1);
        $this->assertTrue(true);
    }

    public function testSkipsRewardRenderWhenDuplicate(): void
    {
        $context = $this->makeContext(memberId: 3, surfaceType: 'page', surfaceId: 8);

        $this->deduplicator
            ->expects('alreadyTrackedReward')
            ->andReturn(true);

        $this->rewardsRepository->shouldNotReceive('trackClick');

        $this->recorder->recordRewardRender(55, null, $context);
        $this->assertTrue(true);
    }

    public function testRewardRenderSkipsDeduplicatorForGuest(): void
    {
        $context = $this->makeContext(memberId: null, surfaceType: 'page', surfaceId: 8);

        $this->deduplicator->shouldNotReceive('alreadyTrackedReward');

        $this->rewardsRepository
            ->expects('trackClick')
            ->once();

        $this->recorder->recordRewardRender(55, null, $context, '', '', 1);
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Reward click
    // -------------------------------------------------------------------------

    public function testRecordsRewardClickWhenNotDuplicate(): void
    {
        $context = $this->makeContext(memberId: 3, surfaceType: 'page', surfaceId: 8);

        $this->deduplicator
            ->expects('alreadyTrackedReward')
            ->with(55, 3, 'click', 'page', 8)
            ->andReturn(false);

        $this->rewardsRepository
            ->expects('trackClick')
            ->once();

        $this->recorder->recordRewardClick(55, null, $context, '', '', 1);
        $this->assertTrue(true);
    }

    public function testSkipsRewardClickWhenDuplicate(): void
    {
        $context = $this->makeContext(memberId: 3, surfaceType: 'page', surfaceId: 8);

        $this->deduplicator
            ->expects('alreadyTrackedReward')
            ->andReturn(true);

        $this->rewardsRepository->shouldNotReceive('trackClick');

        $this->recorder->recordRewardClick(55, null, $context, '', '', 1);
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Reward claim — dedup is intentionally bypassed, claim() is the guard
    // -------------------------------------------------------------------------

    public function testRecordsRewardClaimSuccessfully(): void
    {
        $reward = Mockery::mock(MemberReward::class)->makePartial();
        $reward->member_id = 10;
        $reward->id = 55;
        $reward->allows('claim')->andReturn(true);

        $this->rewardsRepository
            ->expects('findMemberRewardById')
            ->with(55)
            ->andReturn($reward);

        $this->rewardsRepository
            ->expects('trackClick')
            ->with(55, 10, Mockery::any(), 'claim', '', '')
            ->once();

        // Deduplicator must never be consulted for claims
        $this->deduplicator->shouldNotReceive('alreadyTrackedReward');

        $result = $this->recorder->recordRewardClaim(55, 10, '', '', 1);
        $this->assertTrue(true);

        $this->assertTrue($result);
    }

    public function testRejectClaimForWrongMember(): void
    {
        $reward = Mockery::mock(MemberReward::class)->makePartial();
        $reward->member_id = 99;

        $this->rewardsRepository
            ->expects('findMemberRewardById')
            ->andReturn($reward);

        $this->rewardsRepository->shouldNotReceive('trackClick');

        $result = $this->recorder->recordRewardClaim(55, 10, '', '', 1);

        $this->assertFalse($result);
    }

    public function testRejectClaimWhenRewardNotFound(): void
    {
        $this->rewardsRepository
            ->expects('findMemberRewardById')
            ->andReturn(null);

        $this->rewardsRepository->shouldNotReceive('trackClick');

        $result = $this->recorder->recordRewardClaim(55, 10);

        $this->assertFalse($result);
    }

    public function testRejectClaimWhenClaimFails(): void
    {
        $reward = Mockery::mock(MemberReward::class)->makePartial();
        $reward->member_id = 10;
        $reward->allows('claim')->andReturn(false);

        $this->rewardsRepository
            ->expects('findMemberRewardById')
            ->andReturn($reward);

        $this->rewardsRepository->shouldNotReceive('trackClick');

        $result = $this->recorder->recordRewardClaim(55, 10);

        $this->assertFalse($result);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeContext(?int $memberId, string $surfaceType, int $surfaceId): RenderContext
    {
        return new RenderContext(
            memberId: $memberId,
            plan: 'basic',
            isPaid: false,
            channel: 'web',
            surfaceType: $surfaceType,
            surfaceId: $surfaceId,
            timestamp: now_datetime()
        );
    }
}