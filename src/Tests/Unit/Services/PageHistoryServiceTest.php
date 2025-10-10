<?php

namespace App\Tests\Unit\Services;

use App\Models\Page;
use App\Models\PageHistory;
use App\Repositories\PageHistoryRepository;
use App\Repositories\PageRepository;
use App\Services\PageHistoryService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class PageHistoryServiceTest extends FunctionalTestCase
{
    private $historyRepository;
    private $pageRepository;
    private $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->historyRepository = Mockery::mock(PageHistoryRepository::class);
        $this->pageRepository = Mockery::mock(PageRepository::class);

        $this->service = new PageHistoryService(
            $this->historyRepository,
            $this->pageRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testLogPageCreated()
    {
        $page = $this->createMockPage(1, 'Test Page');

        $this->pageRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($page);

        $expectedHistory = new PageHistory([
            'page_id' => 1,
            'action' => 'created',
            'description' => 'Page created'
        ]);

        $this->historyRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function($data) {
                return $data['page_id'] === 1
                    && $data['action'] === 'created'
                    && $data['description'] === 'Page created';
            }))
            ->andReturn($expectedHistory);

        $result = $this->service->logPageCreated($page);

        $this->assertInstanceOf(PageHistory::class, $result);
    }

    public function testLogPageUpdated()
    {
        $page = $this->createMockPage(1, 'Updated Page');

        $oldData = [
            'title' => 'Old Title',
            'status' => 'draft'
        ];

        $newData = [
            'title' => 'New Title',
            'status' => 'published'
        ];

        $this->pageRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($page);

        $this->historyRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function($data) {
                return $data['page_id'] === 1
                    && $data['action'] === 'updated'
                    && !empty($data['changes']);
            }))
            ->andReturn(new PageHistory());

        $result = $this->service->logPageUpdated(1, $oldData, $newData);

        $this->assertInstanceOf(PageHistory::class, $result);
    }

    public function testLogPagePublished()
    {
        $page = $this->createMockPage(1, 'Test Page');

        $this->pageRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($page);

        $this->historyRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function($data) {
                return $data['action'] === 'published'
                    && $data['description'] === 'Page published';
            }))
            ->andReturn(new PageHistory());

        $result = $this->service->logPagePublished(1);

        $this->assertInstanceOf(PageHistory::class, $result);
    }

    public function testLogPageDuplicated()
    {
        $page = $this->createMockPage(2, 'Duplicated Page');

        $this->pageRepository->shouldReceive('find')
            ->with(2)
            ->once()
            ->andReturn($page);

        $this->historyRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) {
                $changes = is_string($data['changes'])
                    ? json_decode($data['changes'], true)
                    : $data['changes'];

                return $data['page_id'] === 2
                    && $data['action'] === 'duplicated'
                    && isset($changes['source_page_id'])
                    && $changes['source_page_id'] === 1;
            }))
            ->andReturn(new PageHistory());

        $result = $this->service->logPageDuplicated(1, 2);

        $this->assertInstanceOf(PageHistory::class, $result);
    }

    public function testGetPageHistory()
    {
        $mockHistory = collect([
            new PageHistory(['id' => 1, 'action' => 'created']),
            new PageHistory(['id' => 2, 'action' => 'updated'])
        ]);

        $this->historyRepository->shouldReceive('getPageHistory')
            ->with(1, 50)
            ->once()
            ->andReturn($mockHistory);

        $result = $this->service->getPageHistory(1);

        $this->assertCount(2, $result);
    }

    public function testRestoreFromHistory()
    {
        $snapshot = [
            'title' => 'Original Title',
            'slug' => 'original-slug',
            'status' => 'published'
        ];

        $history = new PageHistory([
            'id' => 1,
            'page_id' => 5,
            'snapshot' => $snapshot
        ]);

        $page = $this->createMockPage(5, 'Current Title');

        $this->historyRepository->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($history);

        $this->pageRepository->shouldReceive('find')
            ->with(5)
            ->twice()
            ->andReturn($page);

        $this->pageRepository->shouldReceive('update')
            ->once()
            ->with(5, Mockery::on(function($data) use ($snapshot) {
                return $data['title'] === $snapshot['title']
                    && $data['slug'] === $snapshot['slug'];
            }))
            ->andReturn($page);

        $this->historyRepository->shouldReceive('create')
            ->once()
            ->andReturn(new PageHistory());

        $result = $this->service->restoreFromHistory(1);

        $this->assertInstanceOf(Page::class, $result);
    }

    public function testRestoreThrowsExceptionForMissingSnapshot()
    {
        $history = new PageHistory([
            'id' => 1,
            'page_id' => 5,
            'snapshot' => null
        ]);

        $this->historyRepository->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($history);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('History entry not found or has no snapshot');

        $this->service->restoreFromHistory(1);
    }

    private function createMockPage(int $id, string $title): Page
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = $id;
        $page->title = $title;
        $page->site_id = $this->siteId;
        $page->slug = 'test-slug';
        $page->status = 'published';
        return $page;
    }
}