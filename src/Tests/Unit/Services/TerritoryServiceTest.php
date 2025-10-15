<?php

namespace App\Tests\Unit\Services;

use App\Exceptions\CannotDeleteException;
use App\Framework\Database\Database;
use App\Models\Page;
use App\Models\Territory;
use App\Repositories\PageRepository;
use App\Repositories\TerritoryRepository;
use App\Services\RegionSetService;
use App\Services\TerritoryService;
use Mockery;
use PHPUnit\Framework\TestCase;

class TerritoryServiceTest extends TestCase
{
    private $database;
    private $repository;
    private $service;
    private $pageRepository;


    protected function setUp(): void
    {
        $this->database = $this->createMock(Database::class);
        $this->repository = $this->createMock(TerritoryRepository::class);
        $this->pageRepository = $this->createMock(PageRepository::class);


        $this->service = new TerritoryService(
            $this->database,
            $this->repository,
            $this->pageRepository
        );
    }

    public function testCreateTerritory()
    {
        $data = [
            'name' => 'United Kingdom',
            'code' => 'GB',
            'region_set_id' => 1,
            'site_id' => 1
        ];

        $this->database->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(function ($callback) {
                return $callback();
            });

        $mockTerritory = $this->createMock(Territory::class);
        $mockTerritory->id = 1;

        $this->repository->expects($this->once())
            ->method('create')
            ->with($data)
            ->willReturn($mockTerritory);

        $result = $this->service->create($data);

        $this->assertInstanceOf(Territory::class, $result);
    }

    public function testUpdateTerritory()
    {
        $territoryId = 1;
        $data = [
            'name' => 'Updated UK',
            'code' => 'GB'
        ];

        $mockTerritory = $this->createMock(Territory::class);
        $mockTerritory->id = $territoryId;

        $this->database->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(function ($callback) {
                return $callback();
            });

        $this->repository->expects($this->once())
            ->method('find')
            ->with($territoryId)
            ->willReturn($mockTerritory);

        $this->repository->expects($this->once())
            ->method('update')
            ->with($territoryId, $data)
            ->willReturn($mockTerritory);

        $result = $this->service->update($territoryId, $data);

        $this->assertInstanceOf(Territory::class, $result);
    }

    public function testDeleteTerritoryWithoutDependencies()
    {
        $territoryId = 1;

        $mockTerritory = $this->createMock(Territory::class);
        $mockTerritory->expects($this->once())
            ->method('getPageCount')
            ->willReturn(0);

        $this->repository->expects($this->once())
            ->method('find')
            ->with($territoryId)
            ->willReturn($mockTerritory);

        $this->repository->expects($this->once())
            ->method('delete')
            ->with($territoryId)
            ->willReturn(true);

        $result = $this->service->delete($territoryId);

        $this->assertTrue($result);
    }

    public function testDeleteTerritoryWithDependenciesThrowsException()
    {
        $territoryId = 1;

        $mockTerritory = $this->createMock(Territory::class);
        $mockTerritory->expects($this->once())
            ->method('getPageCount')
            ->willReturn(5);

        $this->repository->expects($this->once())
            ->method('find')
            ->with($territoryId)
            ->willReturn($mockTerritory);

        $this->expectException(CannotDeleteException::class);

        $this->service->delete($territoryId);
    }

    public function testDeleteTerritoryWithReassignment()
    {
        $territoryId = 1;
        $reassignToId = 2;

        $mockTerritory = $this->createMock(Territory::class);
        $mockTerritory->id = $territoryId;
        $mockTerritory->expects($this->once())
            ->method('getPageCount')
            ->willReturn(3);

        $mockReassignTerritory = $this->createMock(Territory::class);
        $mockReassignTerritory->id = $reassignToId;

        $this->repository->expects($this->exactly(2))
            ->method('find')
            ->willReturnMap([
                [$territoryId, $mockTerritory],
                [$reassignToId, $mockReassignTerritory]
            ]);

        $this->database->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(function ($callback) {
                return $callback();
            });

        $mockTerritory->expects($this->once())
            ->method('pages')
            ->willReturnSelf();

//        $mockTerritory->expects($this->once())
//            ->method('get')
//            ->willReturn(collect([]));

        $mockTerritory->expects($this->once())
            ->method('delete')
            ->willReturn(true);

        $result = $this->service->delete($territoryId, $reassignToId);

        $this->assertTrue($result);
    }

    public function testCheckDeletable()
    {
        $territoryId = 1;

        $expectedResult = [
            'can_delete' => false,
            'page_count' => 5,
            'requires_reassignment' => true
        ];

        $this->repository->expects($this->once())
            ->method('checkDeletable')
            ->with($territoryId)
            ->willReturn($expectedResult);

        $result = $this->service->checkDeletable($territoryId);

        $this->assertEquals($expectedResult, $result);
    }

    public function testBulkUpdateRegionSet()
    {
        $territoryIds = [1, 2, 3];
        $newRegionSetId = 5;

        $this->repository->expects($this->once())
            ->method('bulkUpdateRegionSet')
            ->with($territoryIds, $newRegionSetId)
            ->willReturn(true);

        $result = $this->service->bulkUpdateRegionSet($territoryIds, $newRegionSetId);

        $this->assertTrue($result);
    }

    public function testReorder()
    {
        $orderedIds = [3, 1, 2];

        $this->repository->expects($this->once())
            ->method('reorderTerritories')
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

        $this->database->expects($this->once())
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

        $service = new TerritoryService(
            $this->database,
            $this->repository,
            $this->pageRepository
        );

        $result = $service->assignPages($regionSetId, $pageIds);

        $this->assertTrue($result);
    }

    public function testSearchAvailablePagesForRegionSet()
    {
        $regionSetId = 1;
        $searchQuery = 'test';

        $mockPages = [
            ['id' => 1, 'title' => 'Test Page 1'],
            ['id' => 2, 'title' => 'Test Page 2']
        ];

        $this->repository->expects($this->once())
            ->method('searchAvailablePages')
            ->with($regionSetId, $searchQuery)
            ->willReturn($mockPages);

        $service = new TerritoryService(
            $this->database,
            $this->repository,
            $this->pageRepository
        );

        $result = $service->searchAvailablePages($regionSetId, $searchQuery);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testUnassignPagesFromTerritory()
    {
        $territoryId = 1;
        $pageIds = [1, 2];

        $mockPage1 = Mockery::mock(Page::class)->makePartial();
        $mockPage1->id = 1;
        $mockPage1->territory_id = $territoryId;
        $mockPage1->shouldReceive('save')->once()->andReturn(true);

        $mockPage2 = Mockery::mock(Page::class)->makePartial();
        $mockPage2->id = 2;
        $mockPage2->territory_id = $territoryId;
        $mockPage2->shouldReceive('save')->once()->andReturn(true);

        $this->database->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(function ($callback) {
                return $callback();
            });

        $this->pageRepository
            ->method('find')
            ->willReturnCallback(function ($id) use ($mockPage1, $mockPage2, $territoryId) {
                $page = match ($id) {
                    1 => $mockPage1,
                    2 => $mockPage2,
                    default => null,
                };
                return $page;
            });

        $result = $this->service->unassignPages($territoryId, $pageIds);

        $this->assertTrue($result);
        $this->assertNull($mockPage1->territory_id);
        $this->assertNull($mockPage2->territory_id);
    }

    public function testUnassignPagesOnlyUnassignsFromCorrectTerritory()
    {
        $territoryId = 1;
        $pageIds = [1, 2];

        $mockPage1 = Mockery::mock(Page::class)->makePartial();
        $mockPage1->id = 1;
        $mockPage1->territory_id = $territoryId; // Belongs to this territory
        $mockPage1->shouldReceive('save')->once()->andReturn(true);

        $mockPage2 = Mockery::mock(Page::class)->makePartial();
        $mockPage2->id = 2;
        $mockPage2->territory_id = 2; // Belongs to different territory
        $mockPage2->shouldReceive('save')->never(); // Should NOT be saved

        $this->database->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(function ($callback) {
                return $callback();
            });

        $this->pageRepository
            ->method('find')
            ->willReturnCallback(function ($id) use ($mockPage1, $mockPage2) {
                return match ($id) {
                    1 => $mockPage1,
                    2 => $mockPage2,
                    default => null,
                };
            });

        $result = $this->service->unassignPages($territoryId, $pageIds);

        $this->assertTrue($result);
        $this->assertNull($mockPage1->territory_id);
        $this->assertEquals(2, $mockPage2->territory_id); // Should remain unchanged
    }
}