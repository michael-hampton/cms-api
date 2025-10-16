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
        $this->databaseMock = $this->createMock(Database::class);
        $this->repository = $this->createMock(RegionSetRepository::class);
        $this->territoryRepository = $this->createMock(TerritoryRepository::class);
        $this->pageRepository = $this->createMock(PageRepository::class);
        $this->pageRegionSetRepository = $this->createMock(PageRegionSetRepository::class);

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

        $this->databaseMock->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(function ($callback) {
                return $callback();
            });

        $mockRegionSet = $this->createMock(RegionSet::class);
        $mockRegionSet->id = 1;

        $this->repository->expects($this->once())
            ->method('findBySlug')
            ->willReturn(null);

        $this->repository->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($data) {
                return $data['slug'] === 'europe';
            }))
            ->willReturn($mockRegionSet);

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

        $this->databaseMock->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(function ($callback) {
                return $callback();
            });

        $mockRegionSet = Mockery::mock(RegionSet::class)->makePartial();
        $mockRegionSet->id = 1;

        $this->repository->expects($this->once())
            ->method('create')
            ->willReturn($mockRegionSet);

        $this->territoryRepository->expects($this->exactly(2))
            ->method('create');

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

        $mockRegionSet = $this->createMock(RegionSet::class);
        $mockRegionSet->id = $regionSetId;
        $mockRegionSet->name = 'Europe';

        $this->databaseMock->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(function ($callback) {
                return $callback();
            });

        $this->repository->expects($this->once())
            ->method('find')
            ->with($regionSetId)
            ->willReturn($mockRegionSet);

        $this->repository->expects($this->once())
            ->method('findBySlug')
            ->willReturn(null);

        $this->repository->expects($this->once())
            ->method('update')
            ->with($regionSetId, $this->anything())
            ->willReturn($mockRegionSet);

        $result = $this->service->update($regionSetId, $data);

        $this->assertInstanceOf(RegionSet::class, $result);
    }

    public function testDeleteRegionSetWithoutDependencies()
    {
        $regionSetId = 1;

        $mockRegionSet = $this->createMock(RegionSet::class);
        $mockRegionSet->expects($this->once())
            ->method('getTerritoryCount')
            ->willReturn(0);
        $mockRegionSet->expects($this->once())
            ->method('getPageCount')
            ->willReturn(0);

        $this->repository->expects($this->once())
            ->method('find')
            ->with($regionSetId)
            ->willReturn($mockRegionSet);

        $this->repository->expects($this->once())
            ->method('delete')
            ->with($regionSetId)
            ->willReturn(true);

        $result = $this->service->delete($regionSetId);

        $this->assertTrue($result);
    }

    public function testDeleteRegionSetWithDependenciesThrowsException()
    {
        $regionSetId = 1;

        $mockRegionSet = $this->createMock(RegionSet::class);
        $mockRegionSet->expects($this->once())
            ->method('getTerritoryCount')
            ->willReturn(5);

        $this->repository->expects($this->once())
            ->method('find')
            ->with($regionSetId)
            ->willReturn($mockRegionSet);

        $this->expectException(CannotDeleteException::class);

        $this->service->delete($regionSetId);
    }

    public function testDeleteRegionSetWithReassignment()
    {
        $regionSetId = 1;
        $reassignToId = 2;

        $mockRegionSet = $this->createMock(RegionSet::class);
        $mockRegionSet->id = $regionSetId;
        $mockRegionSet->expects($this->once())
            ->method('getTerritoryCount')
            ->willReturn(3);
        $mockRegionSet->expects($this->once())
            ->method('getPageCount')
            ->willReturn(2);

        $mockReassignRegionSet = $this->createMock(RegionSet::class);
        $mockReassignRegionSet->id = $reassignToId;

        $this->repository->expects($this->exactly(2))
            ->method('find')
            ->willReturnMap([
                [$regionSetId, $mockRegionSet],
                [$reassignToId, $mockReassignRegionSet]
            ]);

        $this->databaseMock->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(function ($callback) {
                return $callback();
            });

        $this->territoryRepository->expects($this->once())
            ->method('getByRegionSet')
            ->willReturn(collect([]));

        $mockRegionSet->expects($this->once())
            ->method('pages')
            ->willReturnSelf();

//        $mockRegionSet->expects($this->once())
//            ->method('get')
//            ->willReturn(collect([]));

        $mockRegionSet->expects($this->once())
            ->method('delete')
            ->willReturn(true);

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

        $this->repository->expects($this->once())
            ->method('checkDeletable')
            ->with($regionSetId)
            ->willReturn($expectedResult);

        $result = $this->service->checkDeletable($regionSetId);

        $this->assertEquals($expectedResult, $result);
    }

    public function testDuplicateRegionSet()
    {
        $regionSetId = 1;
        $newName = 'Europe Copy';

        $mockOriginalRegionSet = $this->createMock(RegionSet::class);
        $mockOriginalRegionSet->id = $regionSetId;
        $mockOriginalRegionSet->name = 'Europe';
        $mockOriginalRegionSet->description = 'European region';
        $mockOriginalRegionSet->site_id = 1;

        $mockNewRegionSet = $this->createMock(RegionSet::class);
        $mockNewRegionSet->id = 2;

        $this->databaseMock->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(function ($callback) {
                return $callback();
            });

        $this->repository->expects($this->once())
            ->method('findWithRelations')
            ->with($regionSetId)
            ->willReturn($mockOriginalRegionSet);

        $this->repository->expects($this->once())
            ->method('findBySlug')
            ->willReturn(null);

        $this->repository->expects($this->once())
            ->method('create')
            ->willReturn($mockNewRegionSet);

        $mockOriginalRegionSet->territories = collect([]);

        $result = $this->service->duplicate($regionSetId, $newName);

        $this->assertInstanceOf(RegionSet::class, $result);
    }

    public function testReorder()
    {
        $orderedIds = [3, 1, 2];

        $this->repository->expects($this->once())
            ->method('reorderRegionSets')
            ->with($orderedIds)
            ->willReturn(true);

        $result = $this->service->reorder($orderedIds);

        $this->assertTrue($result);
    }

    public function testAssignPagesToRegionSet()
    {
        $regionSetId = 1;
        $pageIds = [1, 2, 3];

        $mockPage1 = Mockery::mock(Page::class)->makePartial();
        $mockPage1->id = 1;
        $mockPage1->shouldReceive('save')->once()->andReturn(true);

        $mockPage2 = Mockery::mock(Page::class)->makePartial();
        $mockPage2->id = 2;
        $mockPage2->shouldReceive('save')->once()->andReturn(true);

        $mockPage3 = Mockery::mock(Page::class)->makePartial();
        $mockPage3->id = 3;
        $mockPage3->shouldReceive('save')->once()->andReturn(true);

        $this->databaseMock->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(function ($callback) {
                return $callback();
            });


        $this->pageRepository
            ->method('find')
            ->willReturnCallback(function ($id) use ($mockPage1, $mockPage2, $mockPage3) {
                return match ($id) {
                    1 => $mockPage1,
                    2 => $mockPage2,
                    3 => $mockPage3,
                    default => null,
                };
            });

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
        $this->databaseMock->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(function ($callback) {
                return $callback();
            });

        // Mock repository behavior
        $this->repository->expects($this->once())
            ->method('findWithRelations')
            ->with($regionSetId)
            ->willReturn($mockOriginalRegionSet);

        $this->repository->expects($this->once())
            ->method('findBySlug')
            ->willReturn(null);

        $this->repository->expects($this->once())
            ->method('create')
            ->willReturn($mockNewRegionSet);

        // Territory repository behavior
        $this->territoryRepository->expects($this->once())
            ->method('findByCode')
            ->with('GB-copy', 1)
            ->willReturn(null);

        $this->territoryRepository->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($data) {
                return $data['code'] === 'GB-copy';
            }));

        // Act
        $result = $this->service->duplicate($regionSetId, $newName);

        // Assert
        $this->assertInstanceOf(RegionSet::class, $result);
    }

    public function testUnassignPagesFromRegionSet()
    {
        $regionSetId = 1;
        $pageIds = [1, 2];

        $this->databaseMock->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(function ($callback) {
                return $callback();
            });

        $this->pageRegionSetRepository->expects($this->once())
            ->method('unassignPages')
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

}