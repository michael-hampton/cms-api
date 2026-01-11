<?php

namespace App\Tests\Unit\Models;

use App\Models\Member;
use App\Models\RewardDefinition;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MemberRewardModelTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testIsPendingReturnsTrueForPendingReward(): void
    {
        $reward = $this->createMemberReward(['status' => 'pending']);

        $this->assertTrue($reward->isPending());
    }

    public function testIsPendingReturnsFalseForClaimedReward(): void
    {
        $reward = $this->createMemberReward(['status' => 'claimed']);

        $this->assertFalse($reward->isPending());
    }

    public function testIsClaimedReturnsTrueForClaimedReward(): void
    {
        $reward = $this->createMemberReward(['status' => 'claimed']);

        $this->assertTrue($reward->isClaimed());
    }

    public function testIsClaimedReturnsFalseForPendingReward(): void
    {
        $reward = $this->createMemberReward(['status' => 'pending']);

        $this->assertFalse($reward->isClaimed());
    }

    public function testIsExpiredReturnsTrueForExpiredStatus(): void
    {
        $reward = $this->createMemberReward(['status' => 'expired']);

        $this->assertTrue($reward->isExpired());
    }

    public function testIsExpiredReturnsTrueWhenExpiredAtPassed(): void
    {
        $reward = $this->createMemberReward([
            'status' => 'pending',
            'expires_at' => now_datetime()->modify('-1 day')->format('Y-m-d H:i:s')
        ]);

        $this->assertTrue($reward->isExpired());
    }

    public function testIsExpiredReturnsFalseForActiveReward(): void
    {
        $reward = $this->createMemberReward([
            'status' => 'pending',
            'expires_at' => now_datetime()->modify('+30 days')->format('Y-m-d H:i:s')
        ]);

        $this->assertFalse($reward->isExpired());
    }

    public function testClaimSuccessfullyClaimsPendingReward(): void
    {
        $reward = $this->createMemberReward([
            'status' => 'pending',
            'expires_at' => now_datetime()->modify('+30 days')->format('Y-m-d H:i:s')
        ]);

        $result = $reward->claim();

        $this->assertTrue($result);
        $reward = $reward->fresh();
        $this->assertEquals('claimed', $reward->status);
        $this->assertNotNull($reward->claimed_at);
    }

    public function testClaimFailsForAlreadyClaimedReward(): void
    {
        $reward = $this->createMemberReward(['status' => 'claimed']);

        $result = $reward->claim();

        $this->assertFalse($result);
    }

    public function testClaimFailsForExpiredReward(): void
    {
        $reward = $this->createMemberReward([
            'status' => 'pending',
            'expires_at' => now_datetime()->modify('-1 day')->format('Y-m-d H:i:s')
        ]);

        $result = $reward->claim();

        $this->assertFalse($result);
    }

    public function testMemberRelationship(): void
    {
        $member = $this->createMember();
        $reward = $this->createMemberReward(['member_id' => $member->id]);

        $relatedMember = $reward->member;

        $this->assertInstanceOf(Member::class, $relatedMember);
        $this->assertEquals($member->id, $relatedMember->id);
    }

    public function testRewardDefinitionRelationship(): void
    {
        $rewardDef = $this->createRewardDefinition();
        $reward = $this->createMemberReward(['reward_definition_id' => $rewardDef->id]);

        $definition = $reward->rewardDefinition;

        $this->assertInstanceOf(RewardDefinition::class, $definition);
        $this->assertEquals($rewardDef->id, $definition->id);
    }

    public function testRewardDataCast(): void
    {
        $data = ['voucher_code' => 'ABC123', 'value' => 50];
        $reward = $this->createMemberReward(['reward_data' => $data]);

        $reward = $reward->fresh();

        $this->assertIsArray($reward->reward_data);
        $this->assertEquals('ABC123', $reward->reward_data['voucher_code']);
        $this->assertEquals(50, $reward->reward_data['value']);
    }
}