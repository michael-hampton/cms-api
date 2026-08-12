<?php

namespace App\Tests\Unit\Services\Rewards;

use App\Enums\Rewards\RewardType;
use App\Enums\Vouchers\VoucherType;
use App\Events\Rewards\RewardAwardedEvent;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\Member;
use App\Models\MemberReward;
use App\Models\RewardDefinition;
use App\Repositories\Rewards\RewardsRepository;
use App\Services\Rewards\Handlers\RewardTypeHandlerFactory;
use App\Services\Rewards\Handlers\VoucherRewardHandler;
use App\Services\Rewards\RewardsService;
use App\Tests\Unit\UnitTestCase;
use App\Tests\Support\CapturingEventDispatcher;
use Mockery;

class RewardsServiceTest extends UnitTestCase
{
    protected int $siteId = 1;

    private $repository;
    private $handlerFactory;
    private $service;
    private Database $databaseMock;
    private CapturingEventDispatcher $events;

    protected function setUp(): void
    {
        $this->siteId = 1;
        $this->repository = Mockery::mock(RewardsRepository::class);
        $this->handlerFactory = Mockery::mock(RewardTypeHandlerFactory::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->events = CapturingEventDispatcher::fake();

        $this->service = new RewardsService(
            $this->repository,
            $this->handlerFactory,
            $this->databaseMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetMemberRewardsCallsRepository(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $siteId = 1;
        $expectedCollection = collect([]);

        $this->repository
            ->shouldReceive('getMemberRewards')
            ->once()
            ->with($member->id, $siteId)
            ->andReturn($expectedCollection);

        $result = $this->service->getMemberRewards($member, $siteId);

        $this->assertSame($expectedCollection, $result);
    }

    public function testGetUnclaimedRewardsCallsRepositoryWithPendingStatus(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $siteId = 1;
        $expectedCollection = collect([]);

        $this->repository
            ->shouldReceive('getMemberRewards')
            ->once()
            ->with($member->id, $siteId, 'pending')
            ->andReturn($expectedCollection);

        $result = $this->service->getUnclaimedRewards($member, $siteId);

        $this->assertSame($expectedCollection, $result);
    }

    public function testClaimRewardUsesTransaction(): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $member = new Member(['id' => 1]);

        $reward = Mockery::mock(MemberReward::class)->makePartial();
        $reward->id = 1;
        $reward->member_id = 1;
        $reward->site_id = $this->siteId;
        $reward->shouldReceive('isExpired')->once()->andReturn(false);
        $reward->shouldReceive('isClaimed')->once()->andReturn(false);
        $reward->shouldReceive('claim')->once()->andReturn(true);

        $this->repository
            ->shouldReceive('findMemberRewardById')
            ->once()
            ->with(1)
            ->andReturn($reward);

        $this->repository
            ->shouldReceive('trackClick')
            ->once()
            ->with(1, 1, $this->siteId, 'claim', null, null);

        $result = $this->service->claimReward(1, $member);

        $this->assertTrue($result['success']);
        $this->assertSame($reward, $result['reward']);
    }

    public function testClaimRewardFailsForExpiredReward(): void
    {
        $member = new Member(['id' => 1]);

        $reward = Mockery::mock(MemberReward::class)->makePartial();
        $reward->member_id = 1;
        $reward->shouldReceive('isExpired')->once()->andReturn(true);

        $this->repository
            ->shouldReceive('findMemberRewardById')
            ->once()
            ->with(1)
            ->andReturn($reward);

        $result = $this->service->claimReward(1, $member);

        $this->assertFalse($result['success']);
        $this->assertEquals('This reward has expired', $result['message']);
    }

    public function testClaimRewardTracksViewForAlreadyClaimed(): void
    {
        $member = new Member(['id' => 1]);

        $reward = Mockery::mock(MemberReward::class)->makePartial();
        $reward->member_id = 1;
        $reward->id = 1;
        $reward->site_id = $this->siteId;
        $reward->shouldReceive('isExpired')->once()->andReturn(false);
        $reward->shouldReceive('isClaimed')->once()->andReturn(true);

        $this->repository
            ->shouldReceive('findMemberRewardById')
            ->once()
            ->with(1)
            ->andReturn($reward);

        $this->repository
            ->shouldReceive('trackClick')
            ->once()
            ->with(1, 1, $this->siteId, 'view', null, null);

        $result = $this->service->claimReward(1, $member);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['already_claimed']);
    }

    public function testGetUnclaimedRewardsUsesPendingStatus(): void
    {
        $member = new Member(['id' => 1]);
        $siteId = 1;
        $expectedCollection = collect([]);

        $this->repository
            ->shouldReceive('getMemberRewards')
            ->once()
            ->with($member->id, $siteId, 'pending')
            ->andReturn($expectedCollection);

        $result = $this->service->getUnclaimedRewards($member, $siteId);

        $this->assertSame($expectedCollection, $result);
    }

    public function testGetRewardStatsCalculatesGiftCardTotal(): void
    {
        $member = new Member(['id' => 1]);

        $reward1 = Mockery::mock(MemberReward::class)->makePartial();
        $reward1->id = 1;
        $reward1->status = 'claimed';
        $reward1->reward_data = ['value' => 25, 'currency' => 'GBP'];
        $reward1->shouldReceive('isClaimed')->andReturn(true);

        $reward2 = Mockery::mock(MemberReward::class)->makePartial();
        $reward2->id = 2;
        $reward2->status = 'claimed';
        $reward2->reward_data = ['value' => 15, 'currency' => 'GBP'];
        $reward2->shouldReceive('isClaimed')->andReturn(true);

        $this->repository
            ->shouldReceive('getMemberRewards')
            ->once()
            ->andReturn(collect([$reward1, $reward2]));

        $result = $this->service->getRewardStats($member, $this->siteId);

        $this->assertEquals(40.0, $result['gift_card_total']);
        $this->assertEquals('GBP', $result['currency']);
        $this->assertEquals('£', $result['currency_symbol']);
    }

    public function testGetTopRewardsExcludesEarnedAndQualified(): void
    {
        $member = new Member(['id' => 1]);

        $definition1 = Mockery::mock(RewardDefinition::class)->makePartial();
        $definition1->id = 1;
        $definition1->shouldReceive('checkCriteria')
            ->once()
            ->andReturn(false); // Not qualified

        $definition2 = Mockery::mock(RewardDefinition::class)->makePartial();
        $definition2->id = 2;
        $definition2->shouldReceive('checkCriteria')
            ->once()
            ->andReturn(true); // Qualified, should be excluded

        $allDefinitions = collect([$definition1, $definition2]);
        $memberRewards = collect([]);

        $this->repository
            ->shouldReceive('getActiveRewardDefinitions')
            ->once()
            ->with($this->siteId)
            ->andReturn($allDefinitions);

        $this->repository
            ->shouldReceive('getMemberRewards')
            ->once()
            ->with($member->id, $this->siteId)
            ->andReturn($memberRewards);

        $result = $this->service->getTopRewards($member, $this->siteId);

        $this->assertCount(1, $result);
        $this->assertEquals(1, $result->first()->id);
    }


    public function testClaimRewardFailsForNonExistentReward(): void
    {
        $member = new Member(['id' => 1]);

        $this->repository
            ->shouldReceive('findMemberRewardById')
            ->once()
            ->with(999)
            ->andReturn(null);

        $result = $this->service->claimReward(999, $member);

        $this->assertFalse($result['success']);
        $this->assertEquals('Reward not found', $result['message']);
    }

    public function testClaimRewardFailsForWrongMember(): void
    {
        $member = new Member(['id' => 1]);

        $reward = Mockery::mock(MemberReward::class)->makePartial();
        $reward->member_id = 2; // Different member

        $this->repository
            ->shouldReceive('findMemberRewardById')
            ->once()
            ->with(1)
            ->andReturn($reward);

        $result = $this->service->claimReward(1, $member);

        $this->assertFalse($result['success']);
        $this->assertEquals('Reward not found', $result['message']);
    }

    public function testClaimRewardHandlesAlreadyClaimed(): void
    {
        $member = new Member(['id' => 1]);

        $reward = Mockery::mock(MemberReward::class)->makePartial();
        $reward->member_id = 1;
        $reward->id = 1;
        $reward->site_id = $this->siteId;
        $reward->shouldReceive('isExpired')->once()->andReturn(false);
        $reward->shouldReceive('isClaimed')->once()->andReturn(true);

        $this->repository
            ->shouldReceive('findMemberRewardById')
            ->once()
            ->with(1)
            ->andReturn($reward);

        $this->repository
            ->shouldReceive('trackClick')
            ->once()
            ->with(1, $this->siteId, 1, 'view', null, null);

        $result = $this->service->claimReward(1, $member);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['already_claimed']);
        $this->assertSame($reward, $result['reward']);
        $this->assertEquals('This reward has already been claimed', $result['message']);
    }

    public function testCheckAndAwardRewardsUsesTransaction(): void
    {
        $member = new Member(['id' => 1]);
        $siteId = 1;

        $rewardDef = Mockery::mock(RewardDefinition::class)->makePartial();
        $rewardDef->id = 1;
        $rewardDef->max_claims_per_member = 1;
        $rewardDef->reward_type = 'points';
        $rewardDef->reward_config = ['points' => 100];
        $rewardDef->shouldReceive('checkCriteria')->with($member)->andReturn(true);

        $definitions = new Collection([$rewardDef]);

        $this->repository
            ->shouldReceive('getActiveRewardDefinitions')
            ->once()
            ->with($siteId)
            ->andReturn($definitions);

        $this->repository
            ->shouldReceive('countMemberRewards')
            ->once()
            ->with($member->id, $rewardDef->id)
            ->andReturn(0);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $handler = Mockery::mock(VoucherRewardHandler::class);
        $handler->shouldReceive('handle')
            ->once()
            ->andReturn([
                'reward_data' => ['points' => 100],
                'expires_at' => null
            ]);

        $this->handlerFactory->shouldReceive('make')
            ->once()
            ->with(Mockery::type(RewardType::class))
            ->andReturn($handler);

        $newReward = Mockery::mock(MemberReward::class);

        $this->repository
            ->shouldReceive('createMemberReward')
            ->once()
            ->andReturn($newReward);

        $result = $this->service->checkAndAwardRewards($member, $siteId);

        $this->assertCount(1, $result);
        $this->assertSame($newReward, $result[0]);
        $this->events->assertDispatched(
            RewardAwardedEvent::class,
            fn(RewardAwardedEvent $event): bool => $event->member === $member
                && $event->reward === $newReward
        );
    }

    public function testCheckAndAwardRewardsSkipsWhenMaxClaimsReached(): void
    {
        $member = new Member(['id' => 1]);
        $siteId = 1;

        $rewardDef = Mockery::mock(RewardDefinition::class)->makePartial();
        $rewardDef->id = 1;
        $rewardDef->max_claims_per_member = 1;

        $definitions = new Collection([$rewardDef]);

        $this->repository
            ->shouldReceive('getActiveRewardDefinitions')
            ->once()
            ->with($siteId)
            ->andReturn($definitions);

        $this->repository
            ->shouldReceive('countMemberRewards')
            ->once()
            ->with($member->id, $rewardDef->id)
            ->andReturn(1); // Already claimed once

        $result = $this->service->checkAndAwardRewards($member, $siteId);

        $this->assertCount(0, $result);
    }

    public function testCheckAndAwardRewardsSkipsWhenCriteriaNotMet(): void
    {
        $member = new Member(['id' => 1]);
        $siteId = 1;

        $rewardDef = Mockery::mock(RewardDefinition::class)->makePartial();
        $rewardDef->id = 1;
        $rewardDef->max_claims_per_member = 1;
        $rewardDef->shouldReceive('checkCriteria')->with($member)->andReturn(false);

        $definitions = new Collection([$rewardDef]);

        $this->repository
            ->shouldReceive('getActiveRewardDefinitions')
            ->once()
            ->with($siteId)
            ->andReturn($definitions);

        $this->repository
            ->shouldReceive('countMemberRewards')
            ->once()
            ->with($member->id, $rewardDef->id)
            ->andReturn(0);

        $result = $this->service->checkAndAwardRewards($member, $siteId);

        $this->assertCount(0, $result);
    }

    public function testGetTopRewards()
    {
        $member = new Member(['id' => 1]);

        $definition1 = Mockery::mock(RewardDefinition::class)->makePartial();
        $definition1->id = 1;
        $definition1->shouldReceive('checkCriteria')
            ->once()
            ->andReturn(false);

        $definition2 = Mockery::mock(RewardDefinition::class)->makePartial();
        $definition2->id = 2;
        $definition2->shouldReceive('checkCriteria')
            ->once()
            ->andReturn(true);

        $allDefinitions = collect([$definition1, $definition2]);
        $memberRewards = collect([]);

        $this->repository
            ->shouldReceive('getActiveRewardDefinitions')
            ->once()
            ->with($this->siteId)
            ->andReturn($allDefinitions);

        $this->repository
            ->shouldReceive('getMemberRewards')
            ->once()
            ->with($member->id, $this->siteId)
            ->andReturn($memberRewards);

        $result = $this->service->getTopRewards($member, $this->siteId);

        $this->assertCount(1, $result);
        $this->assertEquals(1, $result->first()->id);
    }


    public function testGetRewardStatsWithVoucherRewards()
    {
        $member = new Member(['id' => 1]);

        $reward1 = Mockery::mock(MemberReward::class)->makePartial();
        $reward1->id = 1;
        $reward1->status = 'pending';
        $reward1->reward_data = ['value' => 10, 'currency' => 'GBP'];
        $reward1->shouldReceive('isClaimed')->andReturn(false);

        $reward2 = Mockery::mock(MemberReward::class)->makePartial();
        $reward2->id = 2;
        $reward2->status = 'claimed';
        $reward2->reward_data = ['value' => 25, 'currency' => 'GBP'];
        $reward2->shouldReceive('isClaimed')->andReturn(true);

        $reward3 = Mockery::mock(MemberReward::class)->makePartial();
        $reward3->id = 3;
        $reward3->status = 'claimed';
        $reward3->reward_data = ['value' => 15, 'currency' => 'GBP'];
        $reward3->shouldReceive('isClaimed')->andReturn(true);

        $reward4 = Mockery::mock(MemberReward::class)->makePartial();
        $reward4->id = 4;
        $reward4->status = 'expired';
        $reward4->reward_data = ['value' => 20, 'currency' => 'GBP'];
        $reward4->shouldReceive('isClaimed')->andReturn(false);

        $this->repository
            ->shouldReceive('getMemberRewards')
            ->once()
            ->with($member->id, $this->siteId)
            ->andReturn(collect([$reward1, $reward2, $reward3, $reward4]));

        $result = $this->service->getRewardStats($member, $this->siteId);

        $this->assertEquals(1, $result['active_count']);
        $this->assertEquals(2, $result['claimed_count']);
        //$this->assertEquals(40.0, $result['gift_card_total']); //todo
        $this->assertEquals('GBP', $result['currency']);
        $this->assertEquals('£', $result['currency_symbol']);
    }

    public function testGetRewardStatsWithDiscountRewards()
    {
        $member = new Member(['id' => 1]);

        $reward = Mockery::mock(MemberReward::class)->makePartial();
        $reward->id = 1;
        $reward->status = 'claimed';
        $reward->reward_data = [
            'discount_value' => 10,
            'discount_type' => 'fixed',
            'currency' => 'USD',
        ];

        $reward->shouldReceive('isClaimed')
            ->twice()
            ->andReturn(true);

        $this->repository
            ->shouldReceive('getMemberRewards')
            ->once()
            ->with($member->id, $this->siteId)
            ->andReturn(collect([$reward]));

        $result = $this->service->getRewardStats($member, $this->siteId);

        $this->assertEquals(10.0, $result['gift_card_total']);
        $this->assertEquals('USD', $result['currency']);
        $this->assertEquals('$', $result['currency_symbol']);
    }


    public function testGetRewardStatsIgnoresPercentageDiscounts()
    {
        $member = new Member(['id' => 1]);

        $reward = Mockery::mock(MemberReward::class)->makePartial();
        $reward->id = 1;
        $reward->status = 'claimed';
        $reward->reward_data = [
            'discount_value' => 20,
            'discount_type' => VoucherType::Percentage->value,
        ];

        $reward->shouldReceive('isClaimed')
            ->twice()
            ->andReturn(true);

        $this->repository
            ->shouldReceive('getMemberRewards')
            ->once()
            ->with($member->id, $this->siteId)
            ->andReturn(collect([$reward]));

        $result = $this->service->getRewardStats($member, $this->siteId);

        $this->assertEquals(0.0, $result['gift_card_total']);
    }
}
