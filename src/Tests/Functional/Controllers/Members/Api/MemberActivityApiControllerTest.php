<?php

namespace App\Tests\Functional\Controllers\Members\Api;

use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MemberActivityApiControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testIndexReturnsActivityDashboard(): void
    {
        $member = $this->createAuthenticatedMember();
        $this->createMemberActivity(['member_id' => $member->id]);
        $this->createMemberActivity(['member_id' => $member->id]);

        $response = $this->getForSite('/api/member/activity', [], true);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('member', $data['data']);
        $this->assertArrayHasKey('progress', $data['data']);
        $this->assertArrayHasKey('recent_activities', $data['data']);
        $this->assertArrayHasKey('activity_trends', $data['data']);
        $this->assertArrayHasKey('member_badges', $data['data']);
    }

    private function createAuthenticatedMember(array $overrides = [])
    {
        $member = $this->createMember($overrides);
        $this->actingAsMember($member);
        return $member;
    }

    public function testIndexReturnsUnauthorizedWhenNotLoggedIn(): void
    {
        $this->unauthenticateMember();

        $response = $this->getForSite('/api/member/activity', $this->jsonHeaders(), true);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testIndexReturnsActivityDatesFormatted(): void
    {
        $member = $this->createAuthenticatedMember();
        $this->createMemberActivity(['member_id' => $member->id]);

        $response = $this->getForSite('/api/member/activity', [], true);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        foreach ($data['data']['recent_activities'] as $activity) {
            if ($activity['activity_date']) {
                $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $activity['activity_date']);
            }
        }
    }

    public function testIndexReturnsEmptyActivitiesWhenNoneExist(): void
    {
        $this->createAuthenticatedMember();

        $response = $this->getForSite('/api/member/activity', [], true);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data['data']['recent_activities']);
    }

    public function testBadgesReturnsEarnedAndUnearnedBadges(): void
    {
        $member = $this->createAuthenticatedMember();
        $badge = $this->createBadge();
        $this->createMemberBadge(['member_id' => $member->id, 'badge_id' => $badge->id]);

        $response = $this->getForSite('/api/member/badges', [], true);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('earned_badges', $data['data']);
        $this->assertArrayHasKey('unearned_badges', $data['data']);
        $this->assertArrayHasKey('categories', $data['data']);
    }

    public function testBadgesReturnsUnauthorizedWhenNotLoggedIn(): void
    {
        $this->unauthenticateMember();

        $response = $this->getForSite('/api/member/badges', $this->jsonHeaders(), true);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testBadgesReturnsEarnedBadgeInCorrectList(): void
    {
        $member = $this->createAuthenticatedMember();
        $earnedBadge = $this->createBadge(['name' => 'Earned Badge', 'category' => 'engagement']);
        $this->createBadge(['name' => 'Unearned Badge', 'category' => 'engagement']);
        $this->createMemberBadge(['member_id' => $member->id, 'badge_id' => $earnedBadge->id]);

        $response = $this->getForSite('/api/member/badges', [], true);

        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data['data']['earned_badges']);
        $this->assertCount(1, $data['data']['unearned_badges']);
    }

    public function testBadgesReturnsCategoriesFromActiveBadges(): void
    {
        $this->createAuthenticatedMember();
        $this->createBadge(['category' => 'engagement']);
        $this->createBadge(['category' => 'loyalty']);

        $response = $this->getForSite('/api/member/badges', [], true);

        $data = json_decode($response->getContent(), true);
        $this->assertContains('engagement', $data['data']['categories']);
        $this->assertContains('loyalty', $data['data']['categories']);
    }

    private function jsonHeaders(): array
    {
        return [
            'Accept' => 'application/json',
        ];
    }
}
