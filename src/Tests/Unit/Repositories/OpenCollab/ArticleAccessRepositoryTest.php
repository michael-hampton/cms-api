<?php

namespace App\Tests\Unit\Repositories\OpenCollab;

use App\Models\ArticleAccess;
use App\Models\Page;
use App\Models\User;
use App\Repositories\OpenCollab\ArticleAccessRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class ArticleAccessRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private ArticleAccessRepository $repository;
    private Page $page;
    private User $user;

    public function test_has_access_by_user_id_returns_true_when_record_exists(): void
    {
        ArticleAccess::create(['page_id' => $this->page->id, 'user_id' => $this->user->id, 'site_id' => $this->siteId, 'email' => 'test@test.com', 'granted_at' => now()]);

        $this->assertTrue($this->repository->hasAccessByUserId($this->page->id, $this->user->id));
    }

    public function test_has_access_by_user_id_returns_false_when_no_record(): void
    {
        $this->assertFalse($this->repository->hasAccessByUserId(1, 999));
    }

    public function test_has_access_by_user_id_does_not_match_different_page(): void
    {
        ArticleAccess::create(['page_id' => $this->page->id, 'user_id' => $this->user->id, 'site_id' => $this->siteId, 'email' => 'test@test.com', 'granted_at' => now()]);

        $this->assertFalse($this->repository->hasAccessByUserId(2, $this->user->id));
    }

    public function test_has_access_by_email_returns_true_when_record_exists(): void
    {
        ArticleAccess::create(['page_id' => $this->page->id, 'user_id' => $this->user->id, 'site_id' => $this->siteId, 'email' => 'reader@example.com', 'granted_at' => now()]);

        $this->assertTrue($this->repository->hasAccessByEmail(1, 'reader@example.com'));
    }

    public function test_has_access_by_email_returns_false_when_no_record(): void
    {
        $this->assertFalse($this->repository->hasAccessByEmail(1, 'nobody@example.com'));
    }

    public function test_has_access_by_email_does_not_match_different_page(): void
    {
        ArticleAccess::create(['page_id' => $this->page->id, 'user_id' => $this->user->id, 'site_id' => $this->siteId, 'email' => 'reader@example.com', 'granted_at' => now()]);


        $this->assertFalse($this->repository->hasAccessByEmail(2, 'reader@example.com'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ArticleAccessRepository();
        $this->page = $this->createPage();
        $this->user = $this->createUser();
    }
}