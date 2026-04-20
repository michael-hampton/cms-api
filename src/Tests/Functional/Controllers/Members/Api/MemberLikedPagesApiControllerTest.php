<?php

namespace App\Tests\Functional\Controllers\Members\Api;

use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MemberLikedPagesApiControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testIndexReturnsLikedPagesAndTotalCount(): void
    {
        $member = $this->createAuthenticatedMember();
        $this->createPageLike(['member_id' => $member->id]);
        $this->createPageLike(['member_id' => $member->id]);

        $response = $this->getForSite('/api/member/liked-pages', [], true);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('liked_pages', $data['data']);
        $this->assertArrayHasKey('total_likes', $data['data']);
    }

    private function createAuthenticatedMember(array $overrides = [])
    {
        $member = $this->createMember($overrides);
        $this->actingAsMember($member);
        return $member;
    }

    public function testIndexReturnsEmptyWhenNoLikes(): void
    {
        $this->createAuthenticatedMember();

        $response = $this->getForSite('/api/member/liked-pages', [], true);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertCount(0, $data['data']['liked_pages']);
        $this->assertEquals(0, $data['data']['total_likes']);
    }

    public function testIndexReturnsUnauthorizedWhenNotLoggedIn(): void
    {
        $this->unauthenticateMember();

        $response = $this->getForSite('/api/member/liked-pages', [], true);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testIndexFormatsDateFields(): void
    {
        $member = $this->createAuthenticatedMember();
        $this->createPageLike(['member_id' => $member->id]);

        $response = $this->getForSite('/api/member/liked-pages', [], true);

        $data = json_decode($response->getContent(), true);
        foreach ($data['data']['liked_pages'] as $like) {
            if ($like['liked_at']) {
                $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $like['liked_at']);
            }
        }
    }

    public function testIndexOnlyReturnsMembersOwnLikes(): void
    {
        $member = $this->createAuthenticatedMember();
        $otherMember = $this->createMember();

        $this->createPageLike(['member_id' => $member->id]);
        $this->createPageLike(['member_id' => $member->id]);
        $this->createPageLike(['member_id' => $otherMember->id]);

        $response = $this->getForSite('/api/member/liked-pages', [], true);

        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data['data']['liked_pages']);
    }
}