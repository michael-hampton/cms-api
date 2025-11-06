<?php

namespace App\Tests\Unit\Services;

use App\Exceptions\CannotDeleteException;
use App\Framework\Database\Database;
use App\Models\Brand;
use App\Models\Page;
use App\Models\RegionSet;
use App\Models\Territory;
use App\Repositories\PageRegionSetRepository;
use App\Repositories\PageRepository;
use App\Repositories\RegionSetRepository;
use App\Repositories\TerritoryRepository;
use App\Services\RegionSetService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use PHPUnit\Framework\TestCase;

class RegionSetServiceTest extends FunctionalTestCase
{
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

        $this->service = new RegionSetService(
            $this->databaseMock,
            $this->repository,
            $this->territoryRepository,
            $this->pageRepository,
            $this->pageRegionSetRepository
        );

        parent::setUp();
    }

    public function testCreateRegionSetGeneratesSlug()
    {
        $data = [
            'name' => 'Europe',
            'site_id' => 1
        ];

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $mockRegionSet = Mockery::mock(RegionSet::class)->makePartial();
        $mockRegionSet->id = 1;

        $this->repository->shouldReceive('findBySlug')
            ->once()
            ->andReturn(null);

        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['slug'] === 'europe';
            }))
            ->andReturn($mockRegionSet);

        $result = $this->service->create($data);

        $this->assertInstanceOf(RegionSet::class, $result);
    }

    public function testCreateRegionSetWithTerritories()
    {
        $data = [
            'name' => 'Europe',
            'slug' => 'europe',
            'site_id' => 1,
            'territories' => [
                ['name' => 'United Kingdom', 'code' => 'GB'],
                ['name' => 'France', 'code' => 'FR']
            ]
        ];

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $mockRegionSet = Mockery::mock(RegionSet::class)->makePartial();
        $mockRegionSet->id = 1;

        $this->repository->shouldReceive('create')
            ->once()
            ->andReturn($mockRegionSet);

        $this->territoryRepository->shouldReceive('getByRegionSet')
            ->once()
            ->with(1)
            ->andReturn(collect([]));

        $this->territoryRepository->shouldReceive('create')
            ->twice();

        $result = $this->service->create($data);

        $this->assertInstanceOf(RegionSet::class, $result);
    }

    public function testUpdateRegionSet()
    {
        $regionSetId = 1;
        $data = [
            'name' => 'Updated Europe',
            'description' => 'Updated description'
        ];

        $mockRegionSet = Mockery::mock(RegionSet::class)->makePartial();
        $mockRegionSet->id = $regionSetId;
        $mockRegionSet->name = 'Europe';

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->repository->shouldReceive('find')
            ->once()
            ->with($regionSetId)
            ->andReturn($mockRegionSet);

        $this->repository->shouldReceive('findBySlug')
            ->once()
            ->andReturn(null);

        $this->repository->shouldReceive('update')
            ->once()
            ->with($regionSetId, Mockery::any())
            ->andReturn($mockRegionSet);

        $result = $this->service->update($regionSetId, $data);

        $this->assertInstanceOf(RegionSet::class, $result);
    }

    public function testDeleteRegionSetWithoutDependencies()
    {
        $regionSetId = 1;

        $mockRegionSet = Mockery::mock(RegionSet::class);
        $mockRegionSet->shouldReceive('getTerritoryCount')
            ->once()
            ->andReturn(0);
        $mockRegionSet->shouldReceive('getPageCount')
            ->once()
            ->andReturn(0);

        $this->repository->shouldReceive('find')
            ->once()
            ->with($regionSetId)
            ->andReturn($mockRegionSet);

        $this->repository->shouldReceive('delete')
            ->once()
            ->with($regionSetId)
            ->andReturn(true);

        $result = $this->service->delete($regionSetId);

        $this->assertTrue($result);
    }

    public function testDeleteRegionSetWithDependenciesThrowsException()
    {
        $regionSetId = 1;

        $mockRegionSet = Mockery::mock(RegionSet::class)->makePartial();
        $mockRegionSet->shouldReceive('getTerritoryCount')
            ->once()
            ->andReturn(5);

        $this->repository->shouldReceive('find')
            ->once()
            ->with($regionSetId)
            ->andReturn($mockRegionSet);

        $this->expectException(CannotDeleteException::class);

        $this->service->delete($regionSetId);
    }

    public function testDeleteRegionSetWithReassignment()
    {
        $regionSetId = 1;
        $reassignToId = 2;

        $mockRegionSet = Mockery::mock(RegionSet::class)->makePartial();
        $mockRegionSet->id = $regionSetId;
        $mockRegionSet->shouldReceive('getTerritoryCount')
            ->once()
            ->andReturn(3);
        $mockRegionSet->shouldReceive('getPageCount')
            ->once()
            ->andReturn(2);

        $mockReassignRegionSet = Mockery::mock(RegionSet::class)->makePartial();
        $mockReassignRegionSet->id = $reassignToId;

        $this->repository->shouldReceive('find')
            ->with($regionSetId)
            ->andReturn($mockRegionSet);

        $this->repository->shouldReceive('find')
            ->with($reassignToId)
            ->andReturn($mockReassignRegionSet);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->territoryRepository->shouldReceive('getByRegionSet')
            ->once()
            ->andReturn(collect([]));

        $this->repository->shouldReceive('reassignPages')
            ->once()
            ->with($regionSetId, $reassignToId)
            ->andReturn(true);

        $mockRegionSet->shouldReceive('delete')
            ->once()
            ->andReturn(true);

        $result = $this->service->delete($regionSetId, $reassignToId);

        $this->assertTrue($result);
    }

    public function testCheckDeletable()
    {
        $regionSetId = 1;

        $expectedResult = [
            'can_delete' => false,
            'territory_count' => 3,
            'page_count' => 5,
            'requires_reassignment' => true
        ];

        $this->repository->shouldReceive('checkDeletable')
            ->once()
            ->with($regionSetId)
            ->andReturn($expectedResult);

        $result = $this->service->checkDeletable($regionSetId);

        $this->assertEquals($expectedResult, $result);
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

        $result = $this->service->duplicate($regionSetId, $newName);

        $this->assertInstanceOf(RegionSet::class, $result);
    }

    public function testReorder()
    {
        $orderedIds = [3, 1, 2];

        $this->repository->shouldReceive('reorderRegionSets')
            ->once()
            ->with($orderedIds)
            ->andReturn(true);

        $result = $this->service->reorder($orderedIds);

        $this->assertTrue($result);
    }

    public function testAssignPagesToRegionSet()
    {
        $regionSetId = 1;
        $pageIds = [1, 2, 3];

        $mockPage1 = Mockery::mock(Page::class)->makePartial();
        $mockPage1->id = 1;

        $mockPage2 = Mockery::mock(Page::class)->makePartial();
        $mockPage2->id = 2;

        $mockPage3 = Mockery::mock(Page::class)->makePartial();
        $mockPage3->id = 3;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->pageRegionSetRepository->shouldReceive('assignPages')
            ->once()
            ->with($regionSetId, [1, 2, 3], 1);

        $service = new RegionSetService(
            $this->databaseMock,
            $this->repository,
            $this->territoryRepository,
            $this->pageRepository,
            $this->pageRegionSetRepository
        );

        $result = $service->assignPages($regionSetId, $pageIds, 1);

        $this->assertTrue($result);
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
        $result = $this->service->duplicate($regionSetId, $newName);

        // Assert
        $this->assertInstanceOf(RegionSet::class, $result);
    }

    public function testUnassignPagesFromRegionSet()
    {
        $regionSetId = 1;
        $pageIds = [1, 2];

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->pageRegionSetRepository->shouldReceive('unassignPages')
            ->once()
            ->with($regionSetId, $pageIds);

        $result = $this->service->unassignPages($regionSetId, $pageIds);

        $this->assertTrue($result);
    }

//    public function testUnassignPagesOnlyUnassignsFromCorrectRegionSet()
//    {
//        $regionSetId = 1;
//        $pageIds = [1, 2];
//
//        $mockPage1 = Mockery::mock(Page::class)->makePartial();
//        $mockPage1->id = 1;
//        $mockPage1->region_set_id = $regionSetId; // Belongs to this region set
//        $mockPage1->territory_id = 5;
//        $mockPage1->shouldReceive('save')->once()->andReturn(true);
//
//        $mockPage2 = Mockery::mock(Page::class)->makePartial();
//        $mockPage2->id = 2;
//        $mockPage2->region_set_id = 2; // Belongs to different region set
//        $mockPage2->shouldReceive('save')->never(); // Should NOT be saved
//
//        $this->pageRepository
//            ->method('find')
//            ->willReturnCallback(function ($id) use ($mockPage1, $mockPage2) {
//                return match ($id) {
//                    1 => $mockPage1,
//                    2 => $mockPage2,
//                    default => null,
//                };
//            });
//
//        $this->databaseMock->expects($this->once())
//            ->method('transaction')
//            ->willReturnCallback(function ($callback) {
//                return $callback();
//            });
//
//        $result = $this->service->unassignPages($regionSetId, $pageIds);
//
//        $this->assertTrue($result);
//        $this->assertNull($mockPage1->region_set_id);
//        $this->assertNull($mockPage1->territory_id);
//        $this->assertEquals(2, $mockPage2->region_set_id); // Should remain unchanged
//    }

//    public function testUnassignPagesWithNonExistentPages()
//    {
//        $regionSetId = 1;
//        $pageIds = [999]; // Non-existent page
//
//        $this->pageRepository->expects($this->once())
//            ->method('find')
//            ->with(999)
//            ->willReturn(null);
//
//        $this->databaseMock->expects($this->once())
//            ->method('transaction')
//            ->willReturnCallback(function ($callback) {
//                return $callback();
//            });
//
//        $result = $this->service->unassignPages($regionSetId, $pageIds);
//
//        $this->assertTrue($result); // Should still return true even if pages don't exist
//    }

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

        $result = $this->service->bulkDelete([1, 2]);

        $this->assertCount(2, $result['deleted']);
        $this->assertCount(0, $result['failed']);
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

        $result = $this->service->bulkActivate([1, 2]);

        $this->assertCount(2, $result['updated']);
        $this->assertCount(0, $result['failed']);
    }

    public function testBulkDeactivateSuccessfully()
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

        $result = $this->service->bulkDeactivate([1, 2]);

        $this->assertCount(2, $result['updated']);
        $this->assertCount(0, $result['failed']);
    }

}