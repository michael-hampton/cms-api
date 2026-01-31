<?php

namespace App\Tests\Unit\Actions\RegionSet;

use App\Actions\RegionSet\CloneRegionSet;
use App\Framework\Database\Database;
use App\Models\RegionSet;
use App\Models\Territory;
use App\Repositories\Cms\Pages\PageRegionSetRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\RegionSetRepository;
use App\Repositories\Cms\TerritoryRepository;
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

        $this->assertInstanceOf(RegionSet::class, $result['region_set']);
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
        $this->assertInstanceOf(RegionSet::class, $result['region_set']);
    }

    public function testDuplicateRegionSetReturnsDetailedResults()
    {
        $mockOriginalRegionSet = Mockery::mock(RegionSet::class)->makePartial();
        $mockOriginalRegionSet->id = 1;
        $mockOriginalRegionSet->name = 'Europe';
        $mockOriginalRegionSet->description = 'European region';
        $mockOriginalRegionSet->site_id = 1;
        $mockOriginalRegionSet->territories = collect([]);

        $mockNewRegionSet = Mockery::mock(RegionSet::class)->makePartial();
        $mockNewRegionSet->id = 2;

        $this->setCloneHistoryExpectations($mockOriginalRegionSet, $mockNewRegionSet, 1, 2);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository->shouldReceive('findWithRelations')->with(1)->andReturn($mockOriginalRegionSet);
        $this->repository->shouldReceive('findBySlug')->andReturn(null);
        $this->repository->shouldReceive('create')->andReturn($mockNewRegionSet);

        $result = $this->service->handle(1);

        $this->assertArrayHasKey('results', $result);
        $this->assertContains('region_set_created', $result['results']['success']);
        $this->assertContains('clone_history', $result['results']['success']);
        $this->assertEquals(0, $result['results']['territories_cloned']);
        $this->assertEquals(1, $result['original_region_set_id']);
    }

    public function testDuplicateRegionSetWithTerritories()
    {
        $mockOriginalRegionSet = Mockery::mock(RegionSet::class)->makePartial();
        $mockOriginalRegionSet->id = 1;
        $mockOriginalRegionSet->name = 'Europe';
        $mockOriginalRegionSet->description = 'European region';
        $mockOriginalRegionSet->site_id = 1;

        $mockTerritory1 = Mockery::mock(Territory::class)->makePartial();
        $mockTerritory1->id = 10;
        $mockTerritory1->name = 'United Kingdom';
        $mockTerritory1->code = 'GB';
        $mockTerritory1->is_active = true;
        $mockTerritory1->sort_order = 0;
        $mockTerritory1->site_id = 1;

        $mockTerritory2 = Mockery::mock(Territory::class)->makePartial();
        $mockTerritory2->id = 11;
        $mockTerritory2->name = 'France';
        $mockTerritory2->code = 'FR';
        $mockTerritory2->is_active = true;
        $mockTerritory2->sort_order = 1;
        $mockTerritory2->site_id = 1;

        $mockOriginalRegionSet->territories = collect([$mockTerritory1, $mockTerritory2]);

        $mockNewRegionSet = Mockery::mock(RegionSet::class)->makePartial();
        $mockNewRegionSet->id = 2;

        $this->setCloneHistoryExpectations($mockOriginalRegionSet, $mockNewRegionSet, 1, 2);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository->shouldReceive('findWithRelations')->with(1)->andReturn($mockOriginalRegionSet);
        $this->repository->shouldReceive('findBySlug')->andReturn(null);
        $this->repository->shouldReceive('create')->andReturn($mockNewRegionSet);

        $this->territoryRepository->shouldReceive('findByCode')->with('GB-copy', 1)->andReturn(null);
        $this->territoryRepository->shouldReceive('generateUniqueSlug')->with('United Kingdom', 1)->andReturn('united-kingdom');
        $this->territoryRepository->shouldReceive('create')->once()->andReturn($mockTerritory1);

        $this->territoryRepository->shouldReceive('findByCode')->with('FR-copy', 1)->andReturn(null);
        $this->territoryRepository->shouldReceive('generateUniqueSlug')->with('France', 1)->andReturn('france');
        $this->territoryRepository->shouldReceive('create')->once()->andReturn($mockTerritory2);

        $result = $this->service->handle(1, 'Europe Copy');

        $this->assertEquals(2, $result['results']['territories_cloned']);
        $this->assertContains('territories_cloned', $result['results']['success']);
    }

    public function testDuplicateRegionSetThrowsExceptionWhenNotFound()
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository->shouldReceive('findWithRelations')->with(999)->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Region set not found');

        $this->service->handle(999);
    }

    public function testDuplicateRegionSetHandlesTerritoryFailure()
    {
        $mockOriginalRegionSet = Mockery::mock(RegionSet::class)->makePartial();
        $mockOriginalRegionSet->id = 1;
        $mockOriginalRegionSet->name = 'Europe';
        $mockOriginalRegionSet->site_id = 1;

        $mockTerritory1 = Mockery::mock(Territory::class)->makePartial();
        $mockTerritory1->id = 10;
        $mockTerritory1->name = 'United Kingdom';
        $mockTerritory1->code = 'GB';
        $mockTerritory1->site_id = 1;

        $mockTerritory2 = Mockery::mock(Territory::class)->makePartial();
        $mockTerritory2->id = 11;
        $mockTerritory2->name = 'France';
        $mockTerritory2->code = 'FR';
        $mockTerritory2->site_id = 1;

        $mockOriginalRegionSet->territories = collect([$mockTerritory1, $mockTerritory2]);

        $mockNewRegionSet = Mockery::mock(RegionSet::class)->makePartial();
        $mockNewRegionSet->id = 2;

        $this->setCloneHistoryExpectations($mockOriginalRegionSet, $mockNewRegionSet, 1, 2);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository->shouldReceive('findWithRelations')->andReturn($mockOriginalRegionSet);
        $this->repository->shouldReceive('findBySlug')->andReturn(null);
        $this->repository->shouldReceive('create')->andReturn($mockNewRegionSet);

        // First territory succeeds
        $this->territoryRepository->shouldReceive('findByCode')->with('GB-copy', 1)->andReturn(null);
        $this->territoryRepository->shouldReceive('generateUniqueSlug')->with('United Kingdom', 1)->andReturn('uk');
        $this->territoryRepository->shouldReceive('create')->once()->andReturn($mockTerritory1);

        // Second territory fails
        $this->territoryRepository->shouldReceive('findByCode')->with('FR-copy', 1)->andReturn(null);
        $this->territoryRepository->shouldReceive('generateUniqueSlug')->with('France', 1)->andReturn('france');
        $this->territoryRepository->shouldReceive('create')->once()->andThrow(new \Exception('Territory create failed'));

        $result = $this->service->handle(1);

        $this->assertEquals(1, $result['results']['territories_cloned']);
        $this->assertCount(1, $result['results']['failed']);
        $this->assertEquals('clone_territory', $result['results']['failed'][0]['operation']);
        $this->assertEquals(11, $result['results']['failed'][0]['territory_id']);
    }

    public function testDuplicateRegionSetWithCustomName()
    {
        $mockOriginalRegionSet = Mockery::mock(RegionSet::class)->makePartial();
        $mockOriginalRegionSet->id = 1;
        $mockOriginalRegionSet->name = 'Europe';
        $mockOriginalRegionSet->site_id = 1;
        $mockOriginalRegionSet->territories = collect([]);

        $mockNewRegionSet = Mockery::mock(RegionSet::class)->makePartial();
        $mockNewRegionSet->id = 2;

        $this->setCloneHistoryExpectations($mockOriginalRegionSet, $mockNewRegionSet, 1, 2);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository->shouldReceive('findWithRelations')->andReturn($mockOriginalRegionSet);
        $this->repository->shouldReceive('findBySlug')->andReturn(null);
        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['name'] === 'Custom Name';
            }))
            ->andReturn($mockNewRegionSet);

        $result = $this->service->handle(1, 'Custom Name');

        $this->assertInstanceOf(RegionSet::class, $result['region_set']);
    }

    public function testDuplicateRegionSetHandlesSlugCollision()
    {
        $mockOriginalRegionSet = Mockery::mock(RegionSet::class)->makePartial();
        $mockOriginalRegionSet->id = 1;
        $mockOriginalRegionSet->name = 'Europe';
        $mockOriginalRegionSet->site_id = 1;
        $mockOriginalRegionSet->territories = collect([]);

        $mockNewRegionSet = Mockery::mock(RegionSet::class)->makePartial();
        $mockNewRegionSet->id = 2;

        $this->setCloneHistoryExpectations($mockOriginalRegionSet, $mockNewRegionSet, 1, 2);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository->shouldReceive('findWithRelations')->andReturn($mockOriginalRegionSet);

        // First slug exists
        $existingRegionSet = new RegionSet(['id' => 99]);
        $this->repository->shouldReceive('findBySlug')->with('europe-copy')->once()->andReturn($existingRegionSet);

        // Second slug available
        $this->repository->shouldReceive('findBySlug')->with('europe-copy-1')->once()->andReturn(null);

        $this->repository->shouldReceive('create')->andReturn($mockNewRegionSet);

        $result = $this->service->handle(1, 'Europe Copy');

        $this->assertInstanceOf(RegionSet::class, $result['region_set']);
    }



    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}