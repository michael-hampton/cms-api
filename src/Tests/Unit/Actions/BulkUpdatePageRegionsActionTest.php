<?php

namespace App\Tests\Unit\Actions;

use App\Actions\BulkUpdatePageRegions;
use App\Models\Page;
use App\Repositories\Cms\PageRegionSetRepository;
use App\Repositories\Cms\PageRepository;
use App\Repositories\Cms\PageTerritoryRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class BulkUpdatePageRegionsActionTest extends FunctionalTestCase
{
    private $pageRepository;
    private $pageRegionSetRepository;
    private $pageTerritoryRepository;
    private $service;

    public function testBulkUpdatePageRegionsSuccessfully()
    {
        $page1 = Mockery::mock(Page::class)->makePartial();
        $page2 = Mockery::mock(Page::class)->makePartial();

        $this->pageRepository->shouldReceive('find')->with(1)->andReturn($page1);
        $this->pageRepository->shouldReceive('find')->with(2)->andReturn($page2);

        $this->pageRegionSetRepository->shouldReceive('syncRegionSets')
            ->with(1, [10, 20], 1)
            ->once();
        $this->pageRegionSetRepository->shouldReceive('syncRegionSets')
            ->with(2, [10, 20], 1)
            ->once();

        $this->pageTerritoryRepository->shouldReceive('syncTerritories')
            ->with(1, [30, 40], 1)
            ->once();
        $this->pageTerritoryRepository->shouldReceive('syncTerritories')
            ->with(2, [30, 40], 1)
            ->once();

        $results = $this->service->handle([1, 2], [10, 20], [30, 40], 1);

        $this->assertTrue($results[1]['success']);
        $this->assertTrue($results[2]['success']);
    }

    public function testBulkUpdatePageRegionsHandlesPageNotFound()
    {
        $this->pageRepository->shouldReceive('find')->with(999)->andReturn(null);

        $results = $this->service->handle([999], [10], [30], 1);

        $this->assertFalse($results[999]['success']);
        $this->assertEquals('Page not found', $results[999]['error']);
    }

    public function testBulkUpdatePageRegionsHandlesEmptyRegionSets()
    {
        $page1 = Mockery::mock(Page::class)->makePartial();

        $this->pageRepository->shouldReceive('find')->with(1)->andReturn($page1);

        $this->pageTerritoryRepository->shouldReceive('syncTerritories')
            ->with(1, [30], 1)
            ->once();

        $results = $this->service->handle([1], [], [30], 1);

        $this->assertTrue($results[1]['success']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->pageRepository = Mockery::mock(PageRepository::class);
        $this->pageRegionSetRepository = Mockery::mock(PageRegionSetRepository::class);
        $this->pageTerritoryRepository = Mockery::mock(PageTerritoryRepository::class);

        $this->service = new BulkUpdatePageRegions(
            $this->pageRepository,
            $this->pageRegionSetRepository,
            $this->pageTerritoryRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}