<?php

namespace App\Tests\Functional\Controllers\Members\Api;

use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MemberStatsApiControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testStatsReturnsEngagementCounts(): void
    {
        $this->createAuthenticatedMember();

        $response = $this->getForSite('/api/member/dashboard/stats', [], true);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('stats', $data['data']);
    }

    private function createAuthenticatedMember(array $overrides = [])
    {
        $member = $this->createMember($overrides);
        $this->actingAsMember($member);
        return $member;
    }

    public function testStatsIncludesAllExpectedKeys(): void
    {
        $this->createAuthenticatedMember();

        $response = $this->getForSite('/api/member/dashboard/stats', [], true);

        $data = json_decode($response->getContent(), true);
        $stats = $data['data']['stats'];

        $expectedKeys = [
            'orders', 'comments', 'pages_read', 'likes', 'rewards_claimed',
            'articles_gifted', 'articles_received', 'subscriptions',
            'newsletters', 'addresses', 'summary', 'scores', 'behaviour',
            'trends', 'interests', 'flags',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $stats, "Missing stats key: {$key}");
        }
    }

    public function testStatsCountsDefaultToZero(): void
    {
        $this->createAuthenticatedMember();

        $response = $this->getForSite('/api/member/dashboard/stats', [], true);

        $data = json_decode($response->getContent(), true);
        $stats = $data['data']['stats'];

        $this->assertEquals(0, $stats['orders']);
        $this->assertEquals(0, $stats['comments']);
        $this->assertEquals(0, $stats['likes']);
    }

    public function testStatsReflectsExistingData(): void
    {
        $member = $this->createAuthenticatedMember(['email' => 'stats@example.com']);
        $this->createPageLike(['member_id' => $member->id]);
        $this->createPageLike(['member_id' => $member->id]);
        $this->createAddress(['member_id' => $member->id]);

        $response = $this->getForSite('/api/member/dashboard/stats', [], true);

        $data = json_decode($response->getContent(), true);
        $stats = $data['data']['stats'];

        $this->assertEquals(1, $stats['addresses']);
    }
}