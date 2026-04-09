<?php

namespace App\Tests\Unit\Models;

use App\Models\RewardAuditLog;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class RewardAuditLogModelTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testMemberRewardRelationship(): void
    {
        $member = $this->createMember();
        $definition = $this->createRewardDefinition();
        $reward = $this->createMemberReward([
            'member_id' => $member->id,
            'reward_definition_id' => $definition->id,
        ]);

        $log = RewardAuditLog::create([
            'member_reward_id' => $reward->id,
            'action' => 'created',
            'new_status' => 'pending',
        ]);

        $log = $log->fresh(['memberReward']);

        $this->assertNotNull($log->memberReward);
        $this->assertEquals($reward->id, $log->memberReward->id);
    }

    public function testRewardDefinitionRelationship(): void
    {
        $definition = $this->createRewardDefinition();
        $reward = $this->createMemberReward([
            'reward_definition_id' => $definition->id,
        ]);

        $log = RewardAuditLog::create([
            'member_reward_id' => $reward->id,
            'reward_definition_id' => $definition->id,
            'action' => 'created',
            'new_status' => 'pending',
        ]);

        $log = $log->fresh(['rewardDefinition']);

        $this->assertNotNull($log->rewardDefinition);
        $this->assertEquals($definition->id, $log->rewardDefinition->id);
    }

    public function testUserRelationship(): void
    {
        $user = $this->createUser();
        $reward = $this->createMemberReward();

        $log = RewardAuditLog::create([
            'member_reward_id' => $reward->id,
            'user_id' => $user->id,
            'action' => 'updated',
            'new_status' => 'claimed',
        ]);

        $log = $log->fresh(['user']);

        $this->assertNotNull($log->user);
        $this->assertEquals($user->id, $log->user['id']);
    }

    public function testGetChangedFields(): void
    {
        $reward = $this->createMemberReward();

        $log = RewardAuditLog::create([
            'member_reward_id' => $reward->id,
            'action' => 'updated',
            'old_status' => 'pending',
            'new_status' => 'claimed',
            'old_data' => [
                'status' => 'pending',
                'admin_notes' => null,
            ],
            'new_data' => [
                'status' => 'claimed',
                'admin_notes' => 'Verified',
            ],
        ]);

        $changed = $log->getChangedFields();

        $this->assertArrayHasKey('status', $changed);
        $this->assertArrayHasKey('admin_notes', $changed);
        $this->assertEquals('pending', $changed['status']['old']);
        $this->assertEquals('claimed', $changed['status']['new']);
    }

    public function testGetChangedFieldsWithNoChanges(): void
    {
        $reward = $this->createMemberReward();

        $log = RewardAuditLog::create([
            'member_reward_id' => $reward->id,
            'action' => 'viewed',
            'old_data' => null,
            'new_data' => null,
        ]);

        $changed = $log->getChangedFields();

        $this->assertEmpty($changed);
    }

    public function testCastsOldDataToArray(): void
    {
        $reward = $this->createMemberReward();

        $oldData = ['status' => 'pending'];
        $log = RewardAuditLog::create([
            'member_reward_id' => $reward->id,
            'action' => 'updated',
            'old_data' => $oldData,
            'new_data' => ['status' => 'claimed'],
        ]);

        $log = $log->fresh();

        $this->assertIsArray($log->old_data);
        $this->assertEquals('pending', $log->old_data['status']);
    }

    public function testCastsNewDataToArray(): void
    {
        $reward = $this->createMemberReward();

        $newData = ['status' => 'claimed'];
        $log = RewardAuditLog::create([
            'member_reward_id' => $reward->id,
            'action' => 'updated',
            'old_data' => ['status' => 'pending'],
            'new_data' => $newData,
        ]);

        $log = $log->fresh();

        $this->assertIsArray($log->new_data);
        $this->assertEquals('claimed', $log->new_data['status']);
    }

    public function testStoresIpAddress(): void
    {
        $reward = $this->createMemberReward();

        $log = RewardAuditLog::create([
            'member_reward_id' => $reward->id,
            'action' => 'created',
            'new_status' => 'pending',
            'ip_address' => '192.168.1.1',
        ]);

        $this->assertEquals('192.168.1.1', $log->ip_address);
    }

    public function testStoresUserAgent(): void
    {
        $reward = $this->createMemberReward();

        $log = RewardAuditLog::create([
            'member_reward_id' => $reward->id,
            'action' => 'created',
            'new_status' => 'pending',
            'user_agent' => 'Mozilla/5.0',
        ]);

        $this->assertEquals('Mozilla/5.0', $log->user_agent);
    }
}