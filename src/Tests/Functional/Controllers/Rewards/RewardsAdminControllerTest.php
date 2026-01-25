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

    public function testSearchSupportsSearchQuery(): void
    {
        $this->actingAs($this->user);

        $rewardDef = $this->createRewardDefinition();

        $reward1 = $this->createMemberReward([
            'member_id' => $this->member->id,
            'reward_definition_id' => $rewardDef->id,
            'admin_notes' => 'Special case review'
        ]);

        $reward2 = $this->createMemberReward([
            'member_id' => $this->member->id,
            'reward_definition_id' => $rewardDef->id,
            'admin_notes' => 'Normal processing'
        ]);

        $response = $this->getForSite('/api/rewards/search?search=Special');

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertGreaterThanOrEqual(1, count($data['rewards']['data']));
    }

    public function testSearchSupportsSorting(): void
    {
        $this->actingAs($this->user);

        $rewardDef = $this->createRewardDefinition();

        sleep(1); // Ensure different timestamps

        $reward1 = $this->createMemberReward([
            'member_id' => $this->member->id,
            'reward_definition_id' => $rewardDef->id
        ]);

        sleep(1);

        $reward2 = $this->createMemberReward([
            'member_id' => $this->member->id,
            'reward_definition_id' => $rewardDef->id
        ]);

        $response = $this->getForSite('/api/rewards/search?sort_by=created_at&sort_order=desc');

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        // Most recent should be first
        $this->assertEquals($reward2->id, $data['rewards']['data'][0]['id']);
    }

    public function testSearchSupportsPagination(): void
    {
        $this->actingAs($this->user);

        $rewardDef = $this->createRewardDefinition();

        for ($i = 1; $i <= 25; $i++) {
            $this->createMemberReward([
                'member_id' => $this->member->id,
                'reward_definition_id' => $rewardDef->id
            ]);
        }

        $response = $this->getForSite('/api/rewards/search?page=1&per_page=10');

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertCount(10, $data['rewards']['data']);
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

    public function testSearchFiltersByMultipleStatuses(): void
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

        $this->createMemberReward([
            'member_id' => $this->member->id,
            'reward_definition_id' => $rewardDef->id,
            'status' => 'declined'
        ]);

        $response = $this->getForSite('/api/rewards/search?status=pending,claimed');

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);

        foreach ($data['rewards']['data'] as $reward) {
            $this->assertContains($reward['status'], ['pending', 'claimed']);
        }
    }

    public function testSearchFiltersByMemberId(): void
    {
        $this->actingAs($this->user);

        $member2 = $this->createMember();
        $rewardDef = $this->createRewardDefinition();

        $this->createMemberReward([
            'member_id' => $this->member->id,
            'reward_definition_id' => $rewardDef->id
        ]);

        $this->createMemberReward([
            'member_id' => $member2->id,
            'reward_definition_id' => $rewardDef->id
        ]);

        $response = $this->getForSite("/api/rewards/search?member_id={$this->member->id}");

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);

        foreach ($data['rewards']['data'] as $reward) {
            $this->assertEquals($this->member->id, $reward['member_id']);
        }
    }

    public function testSearchFiltersByRewardDefinition(): void
    {
        $this->actingAs($this->user);

        $rewardDef1 = $this->createRewardDefinition();
        $rewardDef2 = $this->createRewardDefinition();

        $this->createMemberReward([
            'member_id' => $this->member->id,
            'reward_definition_id' => $rewardDef1->id
        ]);

        $this->createMemberReward([
            'member_id' => $this->member->id,
            'reward_definition_id' => $rewardDef2->id
        ]);

        $response = $this->getForSite("/api/rewards/search?reward_definition_id={$rewardDef1->id}");

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);

        foreach ($data['rewards']['data'] as $reward) {
            $this->assertEquals($rewardDef1->id, $reward['reward_definition_id']);
        }
    }

    public function testSearchFiltersByDateRange(): void
    {
        $this->actingAs($this->user);

        $rewardDef = $this->createRewardDefinition();

        $oldReward = $this->createMemberReward([
            'member_id' => $this->member->id,
            'reward_definition_id' => $rewardDef->id,
            'created_at' => date('Y-m-d H:i:s', strtotime('-10 days'))
        ]);

        $recentReward = $this->createMemberReward([
            'member_id' => $this->member->id,
            'reward_definition_id' => $rewardDef->id,
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
        ]);

        $dateFrom = date('Y-m-d', strtotime('-5 days'));

        $response = $this->getForSite("/api/rewards/search?date_from=$dateFrom");

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);

        foreach ($data['rewards']['data'] as $reward) {
            $this->assertGreaterThanOrEqual(strtotime($dateFrom), strtotime($reward['created_at']));
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createUser();
        $this->member = $this->createMember();
    }
}