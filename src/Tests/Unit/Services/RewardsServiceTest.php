<?php

namespace App\Tests\Unit\Services;

use App\Framework\Support\Collection;
use App\Models\Member;
use App\Models\MemberReward;
use App\Models\RewardDefinition;
use App\Repositories\Rewards\RewardsRepository;
use App\Services\Rewards\RewardsService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class RewardsServiceTest extends FunctionalTestCase
{
    private $repository;
    private $service;

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

    public function testClaimRewardSuccessfully(): void
    {
        $member = new Member(['id' => 1]);

        $reward = Mockery::mock(MemberReward::class)->makePartial();
        $reward->member_id = 1;
        $reward->shouldReceive('isExpired')->once()->andReturn(false);
        $reward->shouldReceive('isClaimed')->once()->andReturn(false);
        $reward->shouldReceive('claim')->once()->andReturn(true);

        $this->repository
            ->shouldReceive('findMemberRewardById')
            ->once()
            ->with(1)
            ->andReturn($reward);

        $result = $this->service->claimReward(1, $member);

        $this->assertTrue($result['success']);
        $this->assertSame($reward, $result['reward']);
        $this->assertEquals('Reward claimed successfully!', $result['message']);
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
        $reward->shouldReceive('isExpired')->once()->andReturn(false);
        $reward->shouldReceive('isClaimed')->once()->andReturn(true);

        $this->repository
            ->shouldReceive('findMemberRewardById')
            ->once()
            ->with(1)
            ->andReturn($reward);

        $result = $this->service->claimReward(1, $member);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['already_claimed']);
        $this->assertSame($reward, $result['reward']);
        $this->assertEquals('This reward has already been claimed', $result['message']);
    }

    public function testCheckAndAwardRewardsAwardsEligibleRewards(): void
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

        $newReward = Mockery::mock(MemberReward::class);

        $this->repository
            ->shouldReceive('createMemberReward')
            ->once()
            ->andReturn($newReward);

        $result = $this->service->checkAndAwardRewards($member, $siteId);

        $this->assertCount(1, $result);
        $this->assertSame($newReward, $result[0]);
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = Mockery::mock(RewardsRepository::class);
        $this->service = new RewardsService($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}