<?php

namespace App\Tests\Unit\Actions;

use App\Actions\BulkSchedulePages;
use App\Models\Page;
use App\Repositories\PageRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class BulkSchedulePagesActionTest extends FunctionalTestCase
{
    private $pageRepository;
    private $service;

    public function testBulkSchedulePagesSuccessfully()
    {
        $page1 = Mockery::mock(Page::class)->makePartial();
        $page1->id = 1;
        $page1->status = 'draft';

        $page2 = Mockery::mock(Page::class)->makePartial();
        $page2->id = 2;
        $page2->status = 'draft';

        $this->pageRepository->shouldReceive('find')->with(1)->andReturn($page1);
        $this->pageRepository->shouldReceive('find')->with(2)->andReturn($page2);

        $this->pageRepository->shouldReceive('update')
            ->with(1, [
                'status' => 'scheduled',
                'scheduled_at' => '2025-01-15 10:00:00'
            ])
            ->once()
            ->andReturn($page1);

        $this->pageRepository->shouldReceive('update')
            ->with(2, [
                'status' => 'scheduled',
                'scheduled_at' => '2025-01-20 15:00:00'
            ])
            ->once()
            ->andReturn($page2);

        $schedules = [
            ['page_id' => 1, 'scheduled_date' => '2025-01-15 10:00:00'],
            ['page_id' => 2, 'scheduled_date' => '2025-01-20 15:00:00']
        ];

        $results = $this->service->handle($schedules);

        $this->assertTrue($results[1]['success']);
        $this->assertEquals('2025-01-15 10:00:00', $results[1]['scheduled_date']);
        $this->assertTrue($results[2]['success']);
        $this->assertEquals('2025-01-20 15:00:00', $results[2]['scheduled_date']);
    }

    public function testBulkSchedulePagesHandlesNonexistentPage()
    {
        $this->pageRepository->shouldReceive('find')->with(999)->andReturn(null);

        $schedules = [
            ['page_id' => 999, 'scheduled_date' => '2025-01-15 10:00:00']
        ];

        $results = $this->service->handle($schedules);

        $this->assertFalse($results[999]['success']);
        $this->assertEquals('Page not found', $results[999]['error']);
    }

    public function testBulkSchedulePagesHandlesUpdateFailure()
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 1;

        $this->pageRepository->shouldReceive('find')->with(1)->andReturn($page);
        $this->pageRepository->shouldReceive('update')->andReturn(null);

        $schedules = [
            ['page_id' => 1, 'scheduled_date' => '2025-01-15 10:00:00']
        ];

        $results = $this->service->handle($schedules);

        $this->assertFalse($results[1]['success']);
        $this->assertEquals('Failed to update page', $results[1]['error']);
    }

    public function testBulkSchedulePagesHandlesException()
    {
        $this->pageRepository->shouldReceive('find')
            ->with(1)
            ->andThrow(new \Exception('Database error'));

        $schedules = [
            ['page_id' => 1, 'scheduled_date' => '2025-01-15 10:00:00']
        ];

        $results = $this->service->handle($schedules);

        $this->assertFalse($results[1]['success']);
        $this->assertEquals('Database error', $results[1]['error']);
    }

    public function testBulkSchedulePagesWithMixedResults()
    {
        $page1 = Mockery::mock(Page::class)->makePartial();
        $page1->id = 1;

        $this->pageRepository->shouldReceive('find')->with(1)->andReturn($page1);
        $this->pageRepository->shouldReceive('find')->with(2)->andReturn(null);
        $this->pageRepository->shouldReceive('update')->with(1, Mockery::any())->andReturn($page1);

        $schedules = [
            ['page_id' => 1, 'scheduled_date' => '2025-01-15 10:00:00'],
            ['page_id' => 2, 'scheduled_date' => '2025-01-20 15:00:00']
        ];

        $results = $this->service->handle($schedules);

        $this->assertTrue($results[1]['success']);
        $this->assertFalse($results[2]['success']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->pageRepository = Mockery::mock(PageRepository::class);
        $this->service = new BulkSchedulePages($this->pageRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}