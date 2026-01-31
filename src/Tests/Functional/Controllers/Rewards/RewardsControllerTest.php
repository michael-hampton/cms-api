<?php

namespace App\Tests\Functional\Controllers\Rewards;

use App\Models\Member;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class RewardsControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private Member $member;

    public function testIndexRedirectsWhenNotAuthenticated(): void
    {
        $this->unauthenticateMember();

        $response = $this->getForSiteUnauthenticated('/member/rewards');

        $this->assertResponseStatus(302, $response);
        $this->assertStringContainsString('/member/login', $response->getHeaders()['Location'] ?? '');
    }

    public function testIndexDisplaysRewardsForAuthenticatedMember(): void
    {
        $this->actingAsMember($this->member);

        $rewardDef = $this->createRewardDefinition();
        $reward = $this->createMemberReward([
            'member_id' => $this->member->id,
            'reward_definition_id' => $rewardDef->id,
            'status' => 'claimed'
        ]);

        $unclaimedReward = $this->createMemberReward([
            'member_id' => $this->member->id,
            'reward_definition_id' => $rewardDef->id,
            'status' => 'pending'
        ]);

        $response = $this->getForSiteUnauthenticated('/member/rewards');

        $this->assertResponseOk($response);
        $content = $response->getContent();
        $this->assertStringContainsString($reward->status, $content);
    }

    public function testClaimReturnsUnauthorizedWhenNotAuthenticated(): void
    {
        $this->unauthenticateMember();

        $rewardDef = $this->createRewardDefinition();
        $reward = $this->createMemberReward([
            'member_id' => $this->member->id,
            'reward_definition_id' => $rewardDef->id,
            'status' => 'pending'
        ]);

        $response = $this->postForSiteUnauthenticated(
            "/member/rewards/{$reward->id}/claim",
            [],
            [],
            ['Accept' => 'application/json']
        );

        $this->assertResponseStatus(401, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function testClaimSuccessfullyClaimsReward(): void
    {
        $this->actingAsMember($this->member);

        $rewardDef = $this->createRewardDefinition();
        $reward = $this->createMemberReward([
            'member_id' => $this->member->id,
            'reward_definition_id' => $rewardDef->id,
            'status' => 'pending'
        ]);

        $response = $this->postForSiteUnauthenticated(
            "/member/rewards/{$reward->id}/claim",
            [],
            [],
            ['Accept' => 'application/json']
        );

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['data']['success']);

        $reward = $reward->fresh();
        $this->assertEquals('claimed', $reward->status);
        $this->assertNotNull($reward->claimed_at);
    }

    public function testClaimFailsForExpiredReward(): void
    {
        $this->actingAsMember($this->member);

        $rewardDef = $this->createRewardDefinition();
        $reward = $this->createMemberReward([
            'member_id' => $this->member->id,
            'reward_definition_id' => $rewardDef->id,
            'status' => 'pending',
            'expires_at' => now_datetime()->modify('-1 day')->format('Y-m-d H:i:s')
        ]);

        $response = $this->postForSite(
            "/member/rewards/{$reward->id}/claim",
            [],
            [],
            ['Accept' => 'application/json']
        );

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['data']['success']);
        $this->assertStringContainsString('expired', $data['data']['message']);
    }

    public function testClaimFailsForNonExistentReward(): void
    {
        $this->actingAsMember($this->member);

        $response = $this->postForSite(
            "/member/rewards/99999/claim",
            [],
            [],
            ['Accept' => 'application/json']
        );

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['data']['success']);
    }

    public function testClaimRedirectsWithMessageWhenNotJson(): void
    {
        $this->actingAsMember($this->member);

        $rewardDef = $this->createRewardDefinition();
        $reward = $this->createMemberReward([
            'member_id' => $this->member->id,
            'reward_definition_id' => $rewardDef->id,
            'status' => 'pending'
        ]);

        $response = $this->postForSite("/member/rewards/{$reward->id}/claim");

        $this->assertResponseStatus(302, $response);
        $this->assertStringContainsString('/member/rewards', $response->getHeaders()['Location'] ?? '');
    }

    public function testClaimFailsForRewardBelongingToAnotherMember(): void
    {
        $this->actingAsMember($this->member);
        $otherMember = $this->createMember(['email' => 'other@example.com']);

        $rewardDef = $this->createRewardDefinition();
        $reward = $this->createMemberReward([
            'member_id' => $otherMember->id,
            'reward_definition_id' => $rewardDef->id,
            'status' => 'pending'
        ]);

        $response = $this->postForSite(
            "/member/rewards/{$reward->id}/claim",
            [],
            [],
            ['Accept' => 'application/json']
        );

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['data']['success']);
    }

    public function testTrackClickRecordsAction(): void
    {
        $this->actingAsMember($this->member);

        $rewardDef = $this->createRewardDefinition();
        $reward = $this->createMemberReward([
            'member_id' => $this->member->id,
            'reward_definition_id' => $rewardDef->id,
            'status' => 'claimed'
        ]);

        $response = $this->postForSiteUnauthenticated(
            "/member/rewards/{$reward->id}/track/view");

        $this->assertResponseOk($response);

        $this->assertDatabaseHas('reward_clicks', [
            'member_reward_id' => $reward->id,
            'member_id' => $this->member->id,
            'action' => 'view'
        ]);
    }

    public function testTrackClickRequiresValidAction(): void
    {
        $this->actingAsMember($this->member);

        $rewardDef = $this->createRewardDefinition();
        $reward = $this->createMemberReward([
            'member_id' => $this->member->id,
            'reward_definition_id' => $rewardDef->id
        ]);

        $response = $this->postForSiteUnauthenticated(
            "/member/rewards/{$reward->id}/track/invalid_action");

        $this->assertResponseStatus(400, $response);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->member = $this->createMember();
    }
}