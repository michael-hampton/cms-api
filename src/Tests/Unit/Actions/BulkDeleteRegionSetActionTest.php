<?php

namespace App\Tests\Unit\Actions;

use App\Actions\BulkDeleteRegionSet;
use App\Framework\Database\Database;
use App\Models\RegionSet;
use App\Repositories\Cms\PageRegionSetRepository;
use App\Repositories\Cms\PageRepository;
use App\Repositories\Cms\RegionSetRepository;
use App\Repositories\Cms\TerritoryRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Services\Concerns\HasSiteHistory;
use Mockery;

class BulkDeleteRegionSetActionTest extends FunctionalTestCase
{
    use HasSiteHistory;

    private $databaseMock;
    private $repository;
    private $territoryRepository;
    private $service;
    private $pageRepository;
    private $pageRegionSetRepository;

    protected function setUp(): void
    {
        $this->databaseMock = Mockery::mock(Database::class);
        $this->repository = Mockery::mock(RegionSetRepository::class);
        $this->territoryRepository = Mockery::mock(TerritoryRepository::class);
        $this->pageRepository = Mockery::mock(PageRepository::class);
        $this->pageRegionSetRepository = Mockery::mock(PageRegionSetRepository::class);

        $this->service = new BulkDeleteRegionSet(
            $this->databaseMock,
            $this->repository,
            $this->territoryRepository,
            $this->pageRepository,
            $this->pageRegionSetRepository
        );

        parent::setUp();
    }

    public function testBulkDeleteSuccessfully()
    {
        $regionSet1 = Mockery::mock(RegionSet::class)->makePartial();
        $regionSet1->shouldReceive('getTerritoryCount')->once()->andReturn(0);
        $regionSet1->shouldReceive('getPageCount')->once()->andReturn(0);

        $regionSet2 = Mockery::mock(RegionSet::class)->makePartial();
        $regionSet2->shouldReceive('getTerritoryCount')->once()->andReturn(0);
        $regionSet2->shouldReceive('getPageCount')->once()->andReturn(0);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($regionSet1);

        $this->repository->shouldReceive('find')
            ->with(2)
            ->once()
            ->andReturn($regionSet2);

        $this->repository->shouldReceive('delete')
            ->twice()
            ->andReturn(true);

        $result = $this->service->handle([1, 2]);

        $this->assertCount(2, $result['deleted']);
        $this->assertCount(0, $result['failed']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}