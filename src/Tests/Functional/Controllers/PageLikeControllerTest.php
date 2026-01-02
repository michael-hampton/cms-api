<?php

namespace App\Tests\Functional\Controllers;

use App\Models\Member;
use App\Models\Page;
use App\Models\PageLike;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class PageLikeControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private Member $member;
    private Page $page;

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = $this->createMember(['email' => 'test@example.com']);
        $this->page = $this->createPage(['title' => 'Test Page']);
    }

    public function testToggleLikeRequiresAuthentication()
    {
        $this->unauthenticateMember();

        $response = $this->postForSite("/api/pages/like/{$this->page->id}");

        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('logged in', $data['message']);
    }

    public function testToggleLikeAddsLike()
    {
        $this->actingAsMember($this->member);

        $response = $this->postForSite("/api/pages/like/{$this->page->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertTrue($data['data']['liked']);
        $this->assertEquals(1, $data['data']['like_count']);
    }

    public function testToggleLikeRemovesLike()
    {
        $this->actingAsMember($this->member);

        // First like
        PageLike::toggle($this->page->id, $this->member->id, $this->siteId);

        // Then unlike
        $response = $this->postForSite("/api/pages/like/{$this->page->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertFalse($data['data']['liked']);
        $this->assertEquals(0, $data['data']['like_count']);
    }

    public function testToggleLikeReturns404ForInvalidPage()
    {
        $this->actingAsMember($this->member);

        $response = $this->postForSite('/api/pages/like/99999');

        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function testGetLikeStatus()
    {
        $response = $this->getForSite("/api/pages/like-status/{$this->page->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertFalse($data['data']['liked']);
        $this->assertEquals(0, $data['data']['like_count']);
    }

    public function testGetLikeStatusWhenLiked()
    {
        $this->actingAsMember($this->member);

        PageLike::toggle($this->page->id, $this->member->id, $this->siteId);

        $response = $this->getForSite("/api/pages/like-status/{$this->page->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertTrue($data['data']['liked']);
        $this->assertEquals(1, $data['data']['like_count']);
    }

    public function testMultipleMembersCanLikeSamePage()
    {
        $member2 = $this->createMember(['email' => 'test2@example.com']);

        PageLike::toggle($this->page->id, $this->member->id, $this->siteId);
        PageLike::toggle($this->page->id, $member2->id, $this->siteId);

        $response = $this->getForSite("/api/pages/like-status/{$this->page->id}");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(2, $data['data']['like_count']);
    }

    public function testMemberCanLikeMultiplePages()
    {
        $this->actingAsMember($this->member);

        $page2 = $this->createPage(['title' => 'Test Page 2', 'slug' => 'test-page-2']);

        $response = $this->postForSite("/api/pages/like/{$this->page->id}");
        $response = $this->postForSite("/api/pages/like/{$page2->id}");


        $likeCount = PageLike::getMemberLikeCount($this->member->id, $this->siteId);
        $this->assertEquals(2, $likeCount);
    }
}