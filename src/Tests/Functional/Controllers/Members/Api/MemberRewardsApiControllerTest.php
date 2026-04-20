<?php

namespace App\Tests\Functional\Controllers\Members\Api;

use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MemberRewardsApiControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testRewardsReturnsFullRewardData(): void
    {
        $member = $this->createAuthenticatedMember();
        $this->createMemberReward(['member_id' => $member->id]);

        $response = $this->getForSite('/api/member/rewards', [], true);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('stats', $data['data']);
        $this->assertArrayHasKey('unclaimed_rewards', $data['data']);
        $this->assertArrayHasKey('claimed_rewards', $data['data']);
        $this->assertArrayHasKey('top_rewards', $data['data']);
    }

    private function createAuthenticatedMember(array $overrides = [])
    {
        $member = $this->createMember($overrides);
        $this->actingAsMember($member);
        return $member;
    }

    public function testRewardsReturnEmptyWhenNoRewardsExist(): void
    {
        $this->createAuthenticatedMember();

        $response = $this->getForSite('/api/member/rewards', [], true);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data['data']['unclaimed_rewards']);
        $this->assertIsArray($data['data']['claimed_rewards']);
    }

    public function testRewardItemHasExpectedShape(): void
    {
        $member = $this->createAuthenticatedMember();
        $this->createMemberReward(['member_id' => $member->id, 'status' => 'pending']);

        $response = $this->getForSite('/api/member/rewards', [], true);

        $data = json_decode($response->getContent(), true);
        if (!empty($data['data']['unclaimed_rewards'])) {
            $reward = $data['data']['unclaimed_rewards'][0];
            $this->assertArrayHasKey('id', $reward);
            $this->assertArrayHasKey('status', $reward);
            $this->assertArrayHasKey('is_expired', $reward);
            $this->assertArrayHasKey('is_claimed', $reward);
            $this->assertArrayHasKey('definition', $reward);
        }
    }

    public function testClaimRequiresAuthentication(): void
    {
        $this->unauthenticateMember();

        $response = $this->postForSite('/api/member/rewards/1/claim', [], [], [], [], true);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testClaimReturnsResultForValidReward(): void
    {
        $member = $this->createAuthenticatedMember();
        $reward = $this->createMemberReward(['member_id' => $member->id, 'status' => 'pending']);

        $response = $this->postForSite("/api/member/rewards/{$reward->id}/claim", [], [], [], [], true);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testTrackClickRequiresAuthentication(): void
    {
        $this->unauthenticateMember();

        $response = $this->postForSite('/api/member/rewards/1/track/view', [], [], [], [], true);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testTrackClickRejectsInvalidAction(): void
    {
        $member = $this->createAuthenticatedMember();
        $reward = $this->createMemberReward(['member_id' => $member->id]);

        $response = $this->postForSite("/api/member/rewards/{$reward->id}/track/invalid_action", [], [], [], [], true);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testTrackClickReturns404ForNonexistentReward(): void
    {
        $this->createAuthenticatedMember();

        $response = $this->postForSite('/api/member/rewards/99999/track/view', [], [], [], [], true);

        $this->assertEquals(404, $response->getStatusCode());
    }
}