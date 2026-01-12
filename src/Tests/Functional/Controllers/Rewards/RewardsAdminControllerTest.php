<?php

namespace App\Tests\Functional\Controllers\Rewards;

use App\Models\Member;
use App\Models\User;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class RewardsAdminControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private User $user;
    private Member $member;

    public function testSearchReturnsRewardsAndStats(): void
    {
        $this->actingAs($this->user);

        $rewardDef = $this->createRewardDefinition();
        $reward1 = $this->createMemberReward([
            'member_id' => $this->member->id,
            'reward_definition_id' => $rewardDef->id,
            'status' => 'pending'
        ]);
        $reward2 = $this->createMemberReward([
            'member_id' => $this->member->id,
            'reward_definition_id' => $rewardDef->id,
            'status' => 'claimed'
        ]);

        $response = $this->getForSite(
            "/api/rewards/search");

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('rewards', $data);
        $this->assertArrayHasKey('stats', $data);
        $this->assertGreaterThanOrEqual(2, $data['stats']['total']);
    }

    public function testSearchFilterByStatus(): void
    {
        $this->actingAs($this->user);

        $rewardDef = $this->createRewardDefinition();
        $this->createMemberReward([
            'member_id' => $this->member->id,
            'reward_definition_id' => $rewardDef->id,
            'status' => 'pending'
        ]);
        $this->createMemberReward([
            'member_id' => $this->member->id,
            'reward_definition_id' => $rewardDef->id,
            'status' => 'claimed'
        ]);

        $response = $this->getForSite(
            "/api/rewards/search?status=pending",
            [],
            ['Accept' => 'application/json']
        );

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);

        foreach ($data['rewards']['data'] as $reward) {
            $this->assertEquals('pending', $reward['status']);
        }
    }

    public function testShowReturnsRewardDetails(): void
    {
        $this->actingAs($this->user);

        $rewardDef = $this->createRewardDefinition();
        $reward = $this->createMemberReward([
            'member_id' => $this->member->id,
            'reward_definition_id' => $rewardDef->id
        ]);

        $response = $this->getForSite(
            "/api/rewards/{$reward->id}");

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals($reward->id, $data['reward']['id']);
    }

//    public function testSearchFiltersByMemberName(): void
//    {
//        $this->actingAs($this->user);
//
//        $rewardDef = $this->createRewardDefinition();
//        $this->createMemberReward([
//            'member_id' => $this->member->id,
//            'reward_definition_id' => $rewardDef->id
//        ]);
//
//        $response = $this->getForSite(
//            "/api/rewards/search?search=" . urlencode($this->member->first_name));
//
//        $data = json_decode($response->getContent(), true);
//
//        $this->assertTrue($data['success']);
//        $this->assertGreaterThan(0, count($data['rewards']['data']));
//    }

    public function testUpdateSavesAdminNotes(): void
    {
        $this->actingAs($this->user);

        $rewardDef = $this->createRewardDefinition();
        $reward = $this->createMemberReward([
            'member_id' => $this->member->id,
            'reward_definition_id' => $rewardDef->id
        ]);

        $response = $this->putForSite(
            "/api/rewards/{$reward->id}",
            ['admin_notes' => 'Test notes'],
        );

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);

        $updated = $reward->fresh();
        $this->assertEquals('Test notes', $updated->admin_notes);
    }

    public function testDeclineRewardSuccess(): void
    {
        $this->actingAs($this->user);

        $rewardDef = $this->createRewardDefinition();
        $reward = $this->createMemberReward([
            'member_id' => $this->member->id,
            'reward_definition_id' => $rewardDef->id,
            'status' => 'pending'
        ]);

        $response = $this->postForSite(
            "/api/rewards/{$reward->id}/decline",
            [
                'decline_reason' => 'Does not meet criteria',
                'admin_notes' => 'Additional context'
            ]
        );

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);

        $updated = $reward->fresh();
        $this->assertEquals('declined', $updated->status);
        $this->assertEquals('Does not meet criteria', $updated->decline_reason);
        $this->assertEquals($this->user->id, $updated->declined_by_admin_id);
        $this->assertNotNull($updated->declined_at);
    }

    public function testDeclineRequiresReason(): void
    {
        $this->actingAs($this->user);

        $rewardDef = $this->createRewardDefinition();
        $reward = $this->createMemberReward([
            'member_id' => $this->member->id,
            'reward_definition_id' => $rewardDef->id,
            'status' => 'pending'
        ]);

        $response = $this->postForSite(
            "/api/rewards/{$reward->id}/decline");

        $this->assertResponseStatus(400, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('required', $data['message']);
    }

    public function testDeclineFailsForAlreadyDeclinedReward(): void
    {
        $this->actingAs($this->user);

        $rewardDef = $this->createRewardDefinition();
        $reward = $this->createMemberReward([
            'member_id' => $this->member->id,
            'reward_definition_id' => $rewardDef->id,
            'status' => 'declined'
        ]);

        $response = $this->postForSite(
            "/api/rewards/{$reward->id}/decline",
            ['decline_reason' => 'Test']
        );

        $this->assertResponseStatus(400, $response);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createUser();
        $this->member = $this->createMember();
    }
}