<?php

namespace App\Tests\Unit\Repositories\Rewards;

use App\Models\RewardVoucherCode;
use App\Repositories\Rewards\RewardAuditLogRepository;
use App\Repositories\Rewards\RewardDefinitionRepository;
use App\Repositories\Rewards\RewardsRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class RewardsRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private RewardsRepository $repository;
    private RewardAuditLogRepository $auditLogRepository;

    public function testGetActiveRewardDefinitions(): void
    {
        $site = $this->createSite();

        $active1 = $this->createRewardDefinition([
            'site_id' => $site->id,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $active2 = $this->createRewardDefinition([
            'site_id' => $site->id,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $inactive = $this->createRewardDefinition([
            'site_id' => $site->id,
            'is_active' => false,
        ]);

        $definitions = $this->repository->getActiveRewardDefinitions($site->id);

        $this->assertCount(2, $definitions);
        $this->assertEquals($active2->id, $definitions->first()->id); // Sorted by sort_order
    }

    public function testGetMemberRewards(): void
    {
        $member = $this->createMember();
        $site = $this->createSite();
        $definition = $this->createRewardDefinition(['site_id' => $site->id]);

        $reward1 = $this->createMemberReward([
            'member_id' => $member->id,
            'site_id' => $site->id,
            'reward_definition_id' => $definition->id,
            'status' => 'claimed',
        ]);

        $reward2 = $this->createMemberReward([
            'member_id' => $member->id,
            'site_id' => $site->id,
            'reward_definition_id' => $definition->id,
            'status' => 'pending',
        ]);

        // Get all rewards
        $rewards = $this->repository->getMemberRewards($member->id, $site->id);
        $this->assertCount(2, $rewards);

        // Get only claimed
        $claimed = $this->repository->getMemberRewards($member->id, $site->id, 'claimed');
        $this->assertCount(1, $claimed);
        $this->assertEquals('claimed', $claimed->first()->status);
    }

    public function testHasEarnedReward(): void
    {
        $member = $this->createMember();
        $definition = $this->createRewardDefinition();

        $this->assertFalse($this->repository->hasEarnedReward($member->id, $definition->id));

        $this->createMemberReward([
            'member_id' => $member->id,
            'reward_definition_id' => $definition->id,
        ]);

        $this->assertTrue($this->repository->hasEarnedReward($member->id, $definition->id));
    }

    public function testCountMemberRewards(): void
    {
        $member = $this->createMember();
        $definition = $this->createRewardDefinition();

        $this->assertEquals(0, $this->repository->countMemberRewards($member->id, $definition->id));

        $this->createMemberReward([
            'member_id' => $member->id,
            'reward_definition_id' => $definition->id,
        ]);

        $this->createMemberReward([
            'member_id' => $member->id,
            'reward_definition_id' => $definition->id,
        ]);

        $this->assertEquals(2, $this->repository->countMemberRewards($member->id, $definition->id));
    }

    public function testCreateMemberReward(): void
    {
        $member = $this->createMember();
        $site = $this->createSite();
        $definition = $this->createRewardDefinition(['site_id' => $site->id]);

        $data = [
            'member_id' => $member->id,
            'site_id' => $site->id,
            'reward_definition_id' => $definition->id,
            'reward_data' => ['points' => 100],
        ];

        $reward = $this->repository->createMemberReward($data);

        $this->assertNotNull($reward);
        $this->assertEquals($member->id, $reward->member_id);
        $this->assertEquals('pending', $reward->status);
        $this->assertNotNull($reward->earned_at);
    }

    public function testGetAvailableVoucher(): void
    {
        $definition = $this->createRewardDefinition();

        $voucher = RewardVoucherCode::create([
            'reward_definition_id' => $definition->id,
            'is_used' => false,
            'assigned_to_member_id' => null,
            'site_id' => $this->siteId,
            'voucher_code' => 'TESTCODE',
            'provider' => 'test_provider',
            'value' => 100
        ]);

        $used = RewardVoucherCode::create([
            'reward_definition_id' => $definition->id,
            'is_used' => true,
            'site_id' => $this->siteId,
            'voucher_code' => 'TESTCODE2',
            'provider' => 'test_provider',
            'value' => 100
        ]);

        $available = $this->repository->getAvailableVoucher($definition->id);

        $this->assertNotNull($available);
        $this->assertEquals($voucher->id, $available->id);
        $this->assertFalse($available->is_used);
    }

    public function testMarkExpiredRewards(): void
    {
        $site = $this->createSite();
        $member = $this->createMember();
        $definition = $this->createRewardDefinition(['site_id' => $site->id]);

        $expired = $this->createMemberReward([
            'member_id' => $member->id,
            'site_id' => $site->id,
            'reward_definition_id' => $definition->id,
            'status' => 'pending',
            'expires_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);

        $valid = $this->createMemberReward([
            'member_id' => $member->id,
            'site_id' => $site->id,
            'reward_definition_id' => $definition->id,
            'status' => 'pending',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
        ]);

        $count = $this->repository->markExpiredRewards($site->id);

        $this->assertEquals(1, $count);
        $this->assertEquals('expired', $expired->fresh()->status);
        $this->assertEquals('pending', $valid->fresh()->status);
    }

    public function testFindMemberRewardById(): void
    {
        $reward = $this->createMemberReward();

        $found = $this->repository->findMemberRewardById($reward->id);

        $this->assertNotNull($found);
        $this->assertEquals($reward->id, $found->id);
    }

    public function testSearchRewards(): void
    {
        $site = $this->createSite();
        $member = $this->createMember();
        $definition = $this->createRewardDefinition(['site_id' => $site->id]);

        $this->createMemberReward([
            'member_id' => $member->id,
            'site_id' => $site->id,
            'reward_definition_id' => $definition->id,
            'status' => 'claimed',
        ]);

        $this->createMemberReward([
            'member_id' => $member->id,
            'site_id' => $site->id,
            'reward_definition_id' => $definition->id,
            'status' => 'pending',
        ]);

        $result = $this->repository->searchRewards($site->id);

        $this->assertIsArray($result);
        $this->assertEquals(2, $result['total']);
        $this->assertCount(2, $result['data']);
    }

    public function testSearchRewardsWithFilters(): void
    {
        $site = $this->createSite();
        $member = $this->createMember();
        $definition = $this->createRewardDefinition(['site_id' => $site->id]);

        $claimed = $this->createMemberReward([
            'member_id' => $member->id,
            'site_id' => $site->id,
            'reward_definition_id' => $definition->id,
            'status' => 'claimed',
        ]);

        $pending = $this->createMemberReward([
            'member_id' => $member->id,
            'site_id' => $site->id,
            'reward_definition_id' => $definition->id,
            'status' => 'pending',
        ]);

        // Filter by status
        $result = $this->repository->searchRewards($site->id, ['status' => 'claimed']);
        $this->assertEquals(1, $result['total']);

        // Filter by member
        $result = $this->repository->searchRewards($site->id, ['member_id' => $member->id]);
        $this->assertEquals(2, $result['total']);

        // Filter by reward definition
        $result = $this->repository->searchRewards($site->id, ['reward_definition_id' => $definition->id]);
        $this->assertEquals(2, $result['total']);
    }

    public function testGetRewardStats(): void
    {
        $site = $this->createSite();
        $member = $this->createMember();
        $definition = $this->createRewardDefinition(['site_id' => $site->id]);

        $this->createMemberReward([
            'member_id' => $member->id,
            'site_id' => $site->id,
            'reward_definition_id' => $definition->id,
            'status' => 'claimed',
        ]);

        $this->createMemberReward([
            'member_id' => $member->id,
            'site_id' => $site->id,
            'reward_definition_id' => $definition->id,
            'status' => 'pending',
        ]);

        $stats = $this->repository->getRewardStats($site->id);

        $this->assertEquals(2, $stats['total']);
        $this->assertEquals(1, $stats['claimed']);
        $this->assertEquals(1, $stats['pending']);
        $this->assertEquals(0, $stats['expired']);
        $this->assertEquals(0, $stats['declined']);
    }

    public function testTrackClick(): void
    {
        $member = $this->createMember();
        $site = $this->createSite();
        $reward = $this->createMemberReward([
            'member_id' => $member->id,
            'site_id' => $site->id,
        ]);

        $this->repository->trackClick(
            $reward->id,
            $member->id,
            $site->id,
            'view',
            '127.0.0.1',
            'Test User Agent'
        );

        $this->assertDatabaseHas('reward_clicks', [
            'member_reward_id' => $reward->id,
            'member_id' => $member->id,
            'action' => 'view',
        ]);
    }

    public function testGetRewardDefinitionStats(): void
    {
        $site = $this->createSite();
        $member = $this->createMember();

        $definition1 = $this->createRewardDefinition(['site_id' => $site->id]);
        $definition2 = $this->createRewardDefinition(['site_id' => $site->id]);

        $this->createMemberReward([
            'member_id' => $member->id,
            'reward_definition_id' => $definition1->id,
            'status' => 'claimed',
        ]);

        $this->createMemberReward([
            'member_id' => $member->id,
            'reward_definition_id' => $definition2->id,
            'status' => 'pending',
        ]);

        $stats = $this->repository->getRewardDefinitionStats($site->id);

        $this->assertArrayHasKey('total_definitions', $stats);
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('claimed', $stats);
        $this->assertArrayHasKey('pending', $stats);
        $this->assertArrayHasKey('claim_rate', $stats);

        $this->assertEquals(2, $stats['total_definitions']);
        $this->assertEquals(2, $stats['total']);
        $this->assertEquals(1, $stats['claimed']);
        $this->assertEquals(1, $stats['pending']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $rewardDefinitionRepository = new RewardDefinitionRepository();
        $this->auditLogRepository = new RewardAuditLogRepository();
        $this->repository = new RewardsRepository($rewardDefinitionRepository, $this->auditLogRepository);
    }
}