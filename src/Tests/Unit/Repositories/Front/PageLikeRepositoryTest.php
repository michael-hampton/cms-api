<?php

namespace App\Tests\Unit\Repositories\Front;

use App\Models\Member;
use App\Models\Page;
use App\Repositories\Members\PageLikeRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class PageLikeRepositoryTest extends FunctionalTestCase
{
    use CreatesTestData;

    private PageLikeRepository $repository;
    private Page $page;
    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new PageLikeRepository();
        $this->page = $this->createPage();
        $this->member = $this->createMember();
    }

    public function testToggleLike()
    {
        $result = $this->repository->toggleLike($this->page->id, $this->member->id, $this->siteId);

        $this->assertTrue($result['liked']);
        $this->assertEquals(1, $result['count']);

        // Toggle again to unlike
        $result = $this->repository->toggleLike($this->page->id, $this->member->id, $this->siteId);

        $this->assertFalse($result['liked']);
        $this->assertEquals(0, $result['like_count']);
    }

    public function testIsLikedBy()
    {
        $this->assertFalse($this->repository->isLikedBy($this->page->id, $this->member->id, $this->siteId));

        $this->repository->toggleLike($this->page->id, $this->member->id, $this->siteId);

        $this->assertTrue($this->repository->isLikedBy($this->page->id, $this->member->id, $this->siteId));
    }

    public function testGetLikeCount()
    {
        $member2 = $this->createMember(['email' => 'test2@example.com']);

        $this->repository->toggleLike($this->page->id, $this->member->id, $this->siteId);
        $this->repository->toggleLike($this->page->id, $member2->id, $this->siteId);

        $count = $this->repository->getLikeCount($this->page->id);

        $this->assertEquals(2, $count);
    }

    public function testGetMemberLikeCount()
    {
        $page2 = $this->createPage(['slug' => 'test-page-2']);

        $this->repository->toggleLike($this->page->id, $this->member->id, $this->siteId);
        $this->repository->toggleLike($page2->id, $this->member->id, $this->siteId);

        $count = $this->repository->getMemberLikeCount($this->member->id, $this->siteId);

        $this->assertEquals(2, $count);
    }

    public function testGetMemberLikedPages()
    {
        $page2 = $this->createPage(['slug' => 'test-page-2']);
        $page3 = $this->createPage(['slug' => 'test-page-3']);

        $this->repository->toggleLike($this->page->id, $this->member->id, $this->siteId);
        $this->repository->toggleLike($page2->id, $this->member->id, $this->siteId);
        $this->repository->toggleLike($page3->id, $this->member->id, $this->siteId);

        $likedPages = $this->repository->getMemberLikedPages($this->member->id, $this->siteId);

        $this->assertCount(3, $likedPages);
    }

    public function testGetMemberLikedPagesWithLimit()
    {
        $page2 = $this->createPage(['slug' => 'test-page-2']);
        $page3 = $this->createPage(['slug' => 'test-page-3']);

        $this->repository->toggleLike($this->page->id, $this->member->id, $this->siteId);
        $this->repository->toggleLike($page2->id, $this->member->id, $this->siteId);
        $this->repository->toggleLike($page3->id, $this->member->id, $this->siteId);

        $likedPages = $this->repository->getMemberLikedPages($this->member->id, $this->siteId, 2);

        $this->assertCount(2, $likedPages);
    }

    public function testGetPageLikes()
    {
        $member2 = $this->createMember(['email' => 'test2@example.com']);
        $member3 = $this->createMember(['email' => 'test3@example.com']);

        $this->repository->toggleLike($this->page->id, $this->member->id, $this->siteId);
        $this->repository->toggleLike($this->page->id, $member2->id, $this->siteId);
        $this->repository->toggleLike($this->page->id, $member3->id, $this->siteId);

        $likes = $this->repository->getPageLikes($this->page->id);

        $this->assertCount(3, $likes);
    }

    public function testGetPageLikesWithLimit()
    {
        $member2 = $this->createMember(['email' => 'test2@example.com']);
        $member3 = $this->createMember(['email' => 'test3@example.com']);

        $this->repository->toggleLike($this->page->id, $this->member->id, $this->siteId);
        $this->repository->toggleLike($this->page->id, $member2->id, $this->siteId);
        $this->repository->toggleLike($this->page->id, $member3->id, $this->siteId);

        $likes = $this->repository->getPageLikes($this->page->id, 2);

        $this->assertCount(2, $likes);
    }
}