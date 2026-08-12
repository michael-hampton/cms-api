<?php

namespace App\Tests\Unit\Actions\RegionSet;

use App\Actions\RegionSet\BulkActivateRegionSets;
use App\Framework\Database\Database;
use App\Models\RegionSet;
use App\Repositories\Cms\Pages\PageRegionSetRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\RegionSetRepository;
use App\Repositories\Cms\TerritoryRepository;
use App\Tests\Unit\UnitTestCase;
use App\Tests\Unit\Services\Concerns\HasSiteHistory;
use Mockery;

class BulkActivateRegionSetsActionTest extends UnitTestCase
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

        $this->service = new BulkActivateRegionSets(
            $this->databaseMock,
            $this->repository,
        );
    }

    public function testBulkActivateSuccessfully()
    {
        $regionSet1 = Mockery::mock(RegionSet::class)->makePartial();
        $regionSet2 = Mockery::mock(RegionSet::class)->makePartial();

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

        $this->repository->shouldReceive('update')
            ->twice()
            ->andReturn($regionSet1, $regionSet2);

        $result = $this->service->handle([1, 2]);

        $this->assertCount(2, $result['updated']);
        $this->assertCount(0, $result['failed']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}