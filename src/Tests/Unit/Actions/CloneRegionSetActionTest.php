<?php

namespace App\Tests\Unit\Actions;

use App\Actions\CloneRegionSet;
use App\Framework\Database\Database;
use App\Models\RegionSet;
use App\Models\Territory;
use App\Repositories\PageRegionSetRepository;
use App\Repositories\PageRepository;
use App\Repositories\RegionSetRepository;
use App\Repositories\TerritoryRepository;
use App\Services\RegionSetService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Services\Concerns\HasSiteHistory;
use Mockery;

class CloneRegionSetActionTest extends FunctionalTestCase
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

        $this->service = new CloneRegionSet(
            $this->databaseMock,
            $this->repository,
            $this->territoryRepository,
            $this->pageRepository,
            $this->pageRegionSetRepository
        );

        parent::setUp();
    }

    public function testDuplicateRegionSet()
    {
        $regionSetId = 1;
        $newName = 'Europe Copy';

        $mockOriginalRegionSet = Mockery::mock(RegionSet::class)->makePartial();
        $mockOriginalRegionSet->id = $regionSetId;
        $mockOriginalRegionSet->name = 'Europe';
        $mockOriginalRegionSet->description = 'European region';
        $mockOriginalRegionSet->site_id = 1;

        $mockNewRegionSet = Mockery::mock(RegionSet::class)->makePartial();
        $mockNewRegionSet->id = 2;

        $this->setCloneHistoryExpectations($mockOriginalRegionSet, $mockNewRegionSet, 1, 2);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->repository->shouldReceive('findWithRelations')
            ->once()
            ->with($regionSetId)
            ->andReturn($mockOriginalRegionSet);

        $this->repository->shouldReceive('findBySlug')
            ->once()
            ->andReturn(null);

        $this->repository->shouldReceive('create')
            ->once()
            ->andReturn($mockNewRegionSet);

        $mockOriginalRegionSet->territories = collect([]);

        $result = $this->service->handle($regionSetId, $newName);

        $this->assertInstanceOf(RegionSet::class, $result);
    }

    public function testDuplicateRegionSetWithUniqueCodeCheck()
    {
        $regionSetId = 1;
        $newName = 'Europe Copy';

        // Use Mockery for both to handle dynamic props and relations
        $mockOriginalRegionSet = Mockery::mock(RegionSet::class)->makePartial();
        $mockOriginalRegionSet->id = $regionSetId;
        $mockOriginalRegionSet->name = 'Europe';
        $mockOriginalRegionSet->description = 'European region';
        $mockOriginalRegionSet->site_id = 1;
        $mockOriginalRegionSet->code = 'TEST';

        $mockTerritory = Mockery::mock(Territory::class)->makePartial();
        $mockTerritory->name = 'United Kingdom';
        $mockTerritory->code = 'GB';
        $mockTerritory->is_active = true;
        $mockTerritory->sort_order = 0;
        $mockTerritory->site_id = 1;

        // Return territories relation
        $mockOriginalRegionSet
            ->shouldReceive('territories')
            ->andReturn(collect([$mockTerritory]));

        // Mock new region set result
        $mockNewRegionSet = Mockery::mock(RegionSet::class)->makePartial();
        $mockNewRegionSet->id = 2;

        $this->setCloneHistoryExpectations($mockOriginalRegionSet, $mockNewRegionSet, 1, 2);

        // Transaction wrapper
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->repository->shouldReceive('findWithRelations')
            ->once()
            ->with($regionSetId)
            ->andReturn($mockOriginalRegionSet);

        $this->repository->shouldReceive('findBySlug')
            ->once()
            ->with('europe-copy')
            ->andReturn(null);

        $this->repository->shouldReceive('create')
            ->once()
            ->with(['name' => 'Europe Copy', 'description' => 'European region', 'is_active' => false, 'site_id' => 1, 'slug' => 'europe-copy'])
            ->andReturn($mockNewRegionSet);

        $this->territoryRepository->shouldReceive('findByCode')
            ->once()
            ->with('GB-copy', 1)
            ->andReturn(null);

        $this->territoryRepository->shouldReceive('generateUniqueSlug')
            ->once()
            ->with('United Kingdom', 1)
            ->andReturn('united-kingdom');

        $this->territoryRepository->shouldReceive('create')
            ->once()
            ->with([
                'name' => 'United Kingdom',
                'slug' => 'united-kingdom',
                'code' => 'GB-copy',
                'region_set_id' => 2,
                'is_active' => true,
                'sort_order' => 0,
                'site_id' => 1
            ])
            ->andReturn($mockTerritory);

        // Act
        $result = $this->service->handle($regionSetId, $newName);

        // Assert
        $this->assertInstanceOf(RegionSet::class, $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}