<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Events\OpenCollab\PagePublishedByContributorEvent;
use App\Exceptions\OpenCollab\UnauthorisedPageAccessException;
use App\Framework\Events\EventDispatcher;
use App\Models\Page;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\OpenCollab\ActivityRepository;
use App\Services\Cms\Pages\PageService;
use App\Services\OpenCollab\ContributorPageService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class ContributorPageServiceTest extends FunctionalTestCase
{
    private ContributorPageService $service;
    private MockInterface $pageService;
    private MockInterface $pageRepository;
    private MockInterface $eventDispatcher;
    private MockInterface $activityRepository;

    public function test_create_injects_contributor_id_and_delegates_to_page_service(): void
    {
        $requestData = ['site_id' => 1, 'forms' => ['main' => ['title' => 'My Article']]];
        $createdPage = $this->makePage(['contributor_id' => 7, 'status' => 'draft']);

        $this->pageService
            ->shouldReceive('createPageWithAllData')
            ->once()
            ->withArgs(function (array $data, int $siteId): bool {
                return $data['contributor_id'] === 7
                    && $data['is_public_contribution'] === true
                    && $siteId === 1;
            })
            ->andReturn($createdPage);

        $page = $this->service->createPage($requestData, 7, 1);

        $this->assertSame($createdPage, $page);
    }

    private function makePage(array $attributes = []): Page
    {
        $defaults = [
            'id' => 1,
            'site_id' => 1,
            'title' => 'Test Article',
            'status' => 'draft',
            'contributor_id' => 7,
        ];

        $page = new Page(array_merge($defaults, $attributes));
        $page->exists = true;
        return $page;
    }

    // -------------------------------------------------------------------------
    // createPage()
    // -------------------------------------------------------------------------

    public function test_update_succeeds_when_contributor_owns_page(): void
    {
        $existing = $this->makePage(['id' => 10, 'contributor_id' => 7, 'status' => 'draft']);
        $updatedPage = $this->makePage(['id' => 10, 'contributor_id' => 7, 'status' => 'draft']);

        $this->pageRepository
            ->shouldReceive('find')
            ->with(10)
            ->once()
            ->andReturn($existing);

        $this->pageService
            ->shouldReceive('updatePageWithAllData')
            ->once()
            ->andReturn($updatedPage);

        $this->eventDispatcher->shouldNotReceive('dispatch');

        $result = $this->service->updatePage(10, ['site_id' => 1], 7, 1);

        $this->assertSame($updatedPage, $result);
    }

    // -------------------------------------------------------------------------
    // updatePage()
    // -------------------------------------------------------------------------

    public function test_update_throws_when_contributor_does_not_own_page(): void
    {
        $existing = $this->makePage(['id' => 10, 'contributor_id' => 99]); // owned by someone else

        $this->pageRepository->shouldReceive('find')->andReturn($existing);

        $this->pageService->shouldNotReceive('updatePageWithAllData');
        $this->eventDispatcher->shouldNotReceive('dispatch');

        $this->expectException(UnauthorisedPageAccessException::class);

        $this->service->updatePage(10, ['site_id' => 1], 7, 1); // contributor_id = 7
    }

    public function test_update_throws_when_page_not_found(): void
    {
        $this->pageRepository->shouldReceive('find')->andReturn(null);

        $this->pageService->shouldNotReceive('updatePageWithAllData');

        $this->expectException(UnauthorisedPageAccessException::class);

        $this->service->updatePage(999, ['site_id' => 1], 7, 1);
    }

    public function test_emits_published_event_when_page_transitions_from_draft_to_published(): void
    {
        $existing = $this->makePage(['id' => 10, 'contributor_id' => 7, 'status' => 'draft']);
        $updatedPage = $this->makePage(['id' => 10, 'contributor_id' => 7, 'status' => 'published']);

        $this->pageRepository->shouldReceive('find')->andReturn($existing);
        $this->pageService->shouldReceive('updatePageWithAllData')->andReturn($updatedPage);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->withArgs(function ($event): bool {
                return $event instanceof PagePublishedByContributorEvent
                    && $event->contributorId === 7;
            });

        $this->service->updatePage(10, ['site_id' => 1], 7, 1);
        $this->assertTrue(true);
    }

    public function test_does_not_emit_event_when_already_published_page_is_updated(): void
    {
        $existing = $this->makePage(['id' => 10, 'contributor_id' => 7, 'status' => 'published']);
        $updatedPage = $this->makePage(['id' => 10, 'contributor_id' => 7, 'status' => 'published']);

        $this->pageRepository->shouldReceive('find')->andReturn($existing);
        $this->pageService->shouldReceive('updatePageWithAllData')->andReturn($updatedPage);

        $this->eventDispatcher->shouldNotReceive('dispatch');

        $this->service->updatePage(10, ['site_id' => 1], 7, 1);
        $this->assertTrue(true);
    }

    public function test_delete_succeeds_when_contributor_owns_page(): void
    {
        $existing = $this->makePage(['id' => 10, 'contributor_id' => 7]);

        $this->pageRepository->shouldReceive('find')->with(10)->andReturn($existing);

        $this->pageRepository
            ->shouldReceive('delete')
            ->with(10)
            ->once();

        $this->service->deletePage(10, 7);
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // deletePage()
    // -------------------------------------------------------------------------

    public function test_delete_throws_when_contributor_does_not_own_page(): void
    {
        $existing = $this->makePage(['id' => 10, 'contributor_id' => 99]);

        $this->pageRepository->shouldReceive('find')->andReturn($existing);
        $this->pageRepository->shouldNotReceive('delete');

        $this->expectException(UnauthorisedPageAccessException::class);

        $this->service->deletePage(10, 7);
        $this->assertTrue(true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->pageService = Mockery::mock(PageService::class);
        $this->pageRepository = Mockery::mock(PageRepository::class);
        $this->eventDispatcher = Mockery::mock(EventDispatcher::class);
        $this->activityRepository = Mockery::mock(ActivityRepository::class);

        $this->service = new ContributorPageService(
            $this->pageService,
            $this->pageRepository,
            $this->eventDispatcher,
            $this->activityRepository
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}