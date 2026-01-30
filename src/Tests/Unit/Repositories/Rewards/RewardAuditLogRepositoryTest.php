<?php

namespace App\Tests\Unit\Repositories\Rewards;

use App\Models\RewardAuditLog;
use App\Repositories\Rewards\RewardAuditLogRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class RewardAuditLogRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private RewardAuditLogRepository $repository;

    public function testLogAction(): void
    {
        $member = $this->createMember();
        $definition = $this->createRewardDefinition();
        $reward = $this->createMemberReward([
            'member_id' => $member->id,
            'reward_definition_id' => $definition->id,
        ]);

        $user = $this->createUser();

        $log = $this->repository->logAction(
            memberRewardId: $reward->id,
            action: 'created',
            userId: $user->id,
            newStatus: 'pending',
            newData: ['test' => 'data'],
            notes: 'Test note',
            rewardDefinitionId: $definition->id
        );

        $this->assertNotNull($log);
        $this->assertEquals($reward->id, $log->member_reward_id);
        $this->assertEquals('created', $log->action);
        $this->assertEquals($user->id, $log->user_id);
        $this->assertEquals('pending', $log->new_status);
        $this->assertEquals(['test' => 'data'], $log->new_data);
    }

    public function testGetLogsForReward(): void
    {
        $reward = $this->createMemberReward();
        $otherReward = $this->createMemberReward();

        $log1 = RewardAuditLog::create([
            'member_reward_id' => $reward->id,
            'action' => 'created',
            'new_status' => 'pending',
        ]);

        $log2 = RewardAuditLog::create([
            'member_reward_id' => $reward->id,
            'action' => 'updated',
            'old_status' => 'pending',
            'new_status' => 'claimed',
        ]);

        RewardAuditLog::create([
            'member_reward_id' => $otherReward->id,
            'action' => 'created',
            'new_status' => 'pending',
        ]);

        $logs = $this->repository->getLogsForReward($reward->id);

        $this->assertCount(2, $logs);
        $this->assertEquals($log2->id, $logs->first()->id); // Most recent first
    }

    public function testGetLogsByAction(): void
    {
        $reward1 = $this->createMemberReward();
        $reward2 = $this->createMemberReward();

        RewardAuditLog::create([
            'member_reward_id' => $reward1->id,
            'action' => 'claimed',
            'new_status' => 'claimed',
        ]);

        RewardAuditLog::create([
            'member_reward_id' => $reward2->id,
            'action' => 'claimed',
            'new_status' => 'claimed',
        ]);

        RewardAuditLog::create([
            'member_reward_id' => $reward1->id,
            'action' => 'declined',
            'new_status' => 'declined',
        ]);

        $claimedLogs = $this->repository->getLogsByAction('claimed');
        $declinedLogs = $this->repository->getLogsByAction('declined');

        $this->assertCount(2, $claimedLogs);
        $this->assertCount(1, $declinedLogs);
    }

    public function testGetLogsByActionWithLimit(): void
    {
        $reward = $this->createMemberReward();

        for ($i = 0; $i < 150; $i++) {
            RewardAuditLog::create([
                'member_reward_id' => $reward->id,
                'action' => 'updated',
                'new_status' => 'pending',
            ]);
        }

        $logs = $this->repository->getLogsByAction('updated', 50);

        $this->assertCount(50, $logs);
    }

    public function testGetRecentLogs(): void
    {
        $reward1 = $this->createMemberReward();
        $reward2 = $this->createMemberReward();

        $old = RewardAuditLog::create([
            'member_reward_id' => $reward1->id,
            'action' => 'created',
            'new_status' => 'pending',
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
        ]);

        $recent = RewardAuditLog::create([
            'member_reward_id' => $reward2->id,
            'action' => 'claimed',
            'new_status' => 'claimed',
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        ]);

        $logs = $this->repository->getRecentLogs(10);

        $this->assertGreaterThan(0, $logs->count());
        $this->assertEquals($recent->id, $logs->last()->id);
    }

    public function testGetLogsByDateRange(): void
    {
        $reward = $this->createMemberReward();

        $oldLog = RewardAuditLog::create([
            'member_reward_id' => $reward->id,
            'action' => 'created',
            'new_status' => 'pending',
            'created_at' => date('Y-m-d H:i:s', strtotime('-10 days')),
        ]);

        $recentLog = RewardAuditLog::create([
            'member_reward_id' => $reward->id,
            'action' => 'updated',
            'new_status' => 'claimed',
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
        ]);

        $dateFrom = date('Y-m-d H:i:s', strtotime('-5 days'));
        $dateTo = date('Y-m-d H:i:s');

        $logs = $this->repository->getLogsByDateRange($dateFrom, $dateTo);

        $this->assertCount(2, $logs); //todo tis is wrong
        $this->assertEquals($recentLog->id, $logs->last()->id);
    }

    public function testGetLogsByUser(): void
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();
        $reward = $this->createMemberReward();

        RewardAuditLog::create([
            'member_reward_id' => $reward->id,
            'user_id' => $user1->id,
            'action' => 'updated',
            'new_status' => 'claimed',
        ]);

        RewardAuditLog::create([
            'member_reward_id' => $reward->id,
            'user_id' => $user1->id,
            'action' => 'declined',
            'new_status' => 'declined',
        ]);

        RewardAuditLog::create([
            'member_reward_id' => $reward->id,
            'user_id' => $user2->id,
            'action' => 'updated',
            'new_status' => 'pending',
        ]);

        $user1Logs = $this->repository->getLogsByUser($user1->id);
        $user2Logs = $this->repository->getLogsByUser($user2->id);

        $this->assertCount(2, $user1Logs);
        $this->assertCount(1, $user2Logs);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new RewardAuditLogRepository();
    }
}