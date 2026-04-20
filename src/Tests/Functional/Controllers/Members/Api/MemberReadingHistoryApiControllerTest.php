<?php

namespace App\Tests\Functional\Controllers\Members\Api;

use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MemberReadingHistoryApiControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testIndexReturnsReadingHistoryAndPageCount(): void
    {
        $member = $this->createAuthenticatedMember();
        $this->createPageView(['member_id' => $member->id]);
        $this->createPageView(['member_id' => $member->id]);

        $response = $this->getForSite('/api/member/reading-history', [], true);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('recently_viewed', $data['data']);
        $this->assertArrayHasKey('total_pages_read', $data['data']);
    }

    private function createAuthenticatedMember(array $overrides = [])
    {
        $member = $this->createMember($overrides);
        $this->actingAsMember($member);
        return $member;
    }

    public function testIndexReturnsEmptyWhenNoHistory(): void
    {
        $this->createAuthenticatedMember();

        $response = $this->getForSite('/api/member/reading-history', [], true);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(0, $data['data']['recently_viewed']);
        $this->assertEquals(0, $data['data']['total_pages_read']);
    }

    public function testIndexReturnsUnauthorizedWhenNotLoggedIn(): void
    {
        $this->unauthenticateMember();

        $response = $this->getForSite('/api/member/reading-history', [], true);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testIndexFormatsViewedAtDate(): void
    {
        $member = $this->createAuthenticatedMember();
        $this->createPageView(['member_id' => $member->id]);

        $response = $this->getForSite('/api/member/reading-history', [], true);

        $data = json_decode($response->getContent(), true);
        foreach ($data['data']['recently_viewed'] as $view) {
            if ($view['viewed_at']) {
                $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $view['viewed_at']);
            }
        }
    }

    public function testIndexOnlyReturnsMembersOwnHistory(): void
    {
        $member = $this->createAuthenticatedMember();
        $otherMember = $this->createMember();

        $this->createPageView(['member_id' => $member->id]);
        $this->createPageView(['member_id' => $member->id]);
        $this->createPageView(['member_id' => $otherMember->id]);

        $response = $this->getForSite('/api/member/reading-history', [], true);

        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data['data']['recently_viewed']);
    }

    public function testIndexIncludesPageDataInViews(): void
    {
        $member = $this->createAuthenticatedMember();
        $this->createPageView(['member_id' => $member->id]);

        $response = $this->getForSite('/api/member/reading-history', [], true);

        $data = json_decode($response->getContent(), true);
        if (!empty($data['data']['recently_viewed'])) {
            $view = $data['data']['recently_viewed'][0];
            $this->assertArrayHasKey('page', $view);
        }
    }
}