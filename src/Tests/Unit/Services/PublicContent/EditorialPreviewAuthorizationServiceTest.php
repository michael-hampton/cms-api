<?php

namespace App\Tests\Unit\Services\PublicContent;

use App\Models\Page;
use App\Models\User;
use App\Services\OpenCollab\OpenCollabAuthorizationService;
use App\Services\PublicContent\EditorialPreviewAuthorizationService;
use Mockery;
use PHPUnit\Framework\TestCase;

final class EditorialPreviewAuthorizationServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_denies_preview_when_the_page_belongs_to_a_different_site(): void
    {
        $authorization = Mockery::mock(OpenCollabAuthorizationService::class);
        $authorization->shouldReceive('allowsAny')->never();

        $service = new EditorialPreviewAuthorizationService($authorization);

        self::assertFalse($service->canPreview($this->user(1), $this->page(siteId: 2), 1));
    }

    public function test_it_allows_preview_for_a_user_with_an_editor_permission(): void
    {
        $authorization = Mockery::mock(OpenCollabAuthorizationService::class);
        $authorization->shouldReceive('allowsAny')
            ->once()
            ->with(1, 5, [
                'content.edit',
                'content.review',
                'content.approve',
                'content.publish',
                'pages.edit',
                'pages.review',
                'pages.approve',
                'pages.publish',
            ])
            ->andReturn(true);

        $service = new EditorialPreviewAuthorizationService($authorization);

        self::assertTrue($service->canPreview($this->user(1), $this->page(siteId: 5), 5));
    }

    public function test_it_allows_preview_for_the_contributor_with_edit_own_permission(): void
    {
        $authorization = Mockery::mock(OpenCollabAuthorizationService::class);
        $authorization->shouldReceive('allowsAny')->once()->andReturn(false);
        $authorization->shouldReceive('allows')->once()->with(7, 5, 'content.edit_own')->andReturn(true);

        $service = new EditorialPreviewAuthorizationService($authorization);

        self::assertTrue($service->canPreview($this->user(7), $this->page(siteId: 5, contributorId: 7), 5));
    }

    public function test_it_denies_preview_for_edit_own_permission_when_not_the_contributor(): void
    {
        $authorization = Mockery::mock(OpenCollabAuthorizationService::class);
        $authorization->shouldReceive('allowsAny')->once()->andReturn(false);
        $authorization->shouldReceive('allows')->once()->with(7, 5, 'content.edit_own')->andReturn(true);

        $service = new EditorialPreviewAuthorizationService($authorization);

        self::assertFalse($service->canPreview($this->user(7), $this->page(siteId: 5, contributorId: 99), 5));
    }

    public function test_it_denies_preview_when_no_permission_matches(): void
    {
        $authorization = Mockery::mock(OpenCollabAuthorizationService::class);
        $authorization->shouldReceive('allowsAny')->once()->andReturn(false);
        $authorization->shouldReceive('allows')->once()->andReturn(false);

        $service = new EditorialPreviewAuthorizationService($authorization);

        self::assertFalse($service->canPreview($this->user(7), $this->page(siteId: 5, contributorId: 99), 5));
    }

    private function user(int $id): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = $id;

        return $user;
    }

    private function page(int $siteId, ?int $contributorId = null): Page
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->site_id = $siteId;
        $page->contributor_id = $contributorId;

        return $page;
    }
}