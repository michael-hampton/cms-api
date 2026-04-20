<?php

namespace App\Tests\Functional\Controllers\Members\Api;

use App\Models\Comment;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MemberCommentsApiControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testIndexReturnsCommentsForMember(): void
    {
        $member = $this->createMember();
        $this->actingAsMember($member);
        $this->createComment(['member_id' => $member->id, 'email' => $member->email]);
        $this->createComment(['member_id' => $member->id, 'email' => $member->email]);

        $response = $this->getForSite('/api/member/comments', [], true);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('comments', $data['data']);
        $this->assertCount(2, $data['data']['comments']);
    }

    public function testIndexReturnsOnlyCommentsMatchingMemberEmail(): void
    {
        $member = $this->createMember();
        $this->actingAsMember($member);
        $otherMember = $this->createMember();

        $this->createComment(['member_id' => $member->id, 'email' => $member->email]);
        $this->createComment(['member_id' => $otherMember->id, 'email' => $otherMember->email]);

        $response = $this->getForSite('/api/member/comments', [], true);

        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data['data']['comments']);
    }

    public function testIndexReturnsUnauthorizedWhenNotLoggedIn(): void
    {
        $this->unauthenticateMember();

        $response = $this->getForSite('/api/member/comments');

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testDestroyDeletesOwnComment(): void
    {
        $member = $this->createAuthenticatedMember(['email' => 'owner@example.com']);
        $comment = $this->createComment(['member_id' => $member->id, 'email' => $member->email]);

        $response = $this->deleteForSite("/api/member/comments/{$comment->id}", [], true);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertNull(Comment::find($comment->id));
    }

    private function createAuthenticatedMember(array $overrides = [])
    {
        $member = $this->createMember($overrides);
        $this->actingAsMember($member);
        return $member;
    }

    public function testDestroyReturns404ForOtherMembersComment(): void
    {
        $member = $this->createAuthenticatedMember(['email' => 'me@example.com']);
        $otherMember = $this->createMember(['email' => 'other@example.com']);
        $comment = $this->createComment(['member_id' => $otherMember->id, 'email' => 'other@example.com']);

        $response = $this->deleteForSite("/api/member/comments/{$comment->id}", [], true);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDestroyReturns404ForNonexistentComment(): void
    {
        $this->createAuthenticatedMember();

        $response = $this->deleteForSite('/api/member/comments/99999', [], true);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDestroyRequiresAuthentication(): void
    {
        $this->unauthenticateMember();
        $member = $this->createMember(['email' => 'someone@example.com']);
        $comment = $this->createComment(['member_id' => $member->id, 'email' => $member->email]);

        $response = $this->deleteForSite("/api/member/comments/{$comment->id}", [], true);

        $this->assertEquals(401, $response->getStatusCode());
    }
}