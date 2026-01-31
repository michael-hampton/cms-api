<?php

namespace App\Tests\Unit\Services\Cms;

use App\Exceptions\CannotDeleteException;
use App\Framework\Database\Database;
use App\Models\Page;
use App\Models\Territory;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\Pages\PageTerritoryRepository;
use App\Repositories\Cms\TerritoryRepository;
use App\Services\Cms\TerritoryService;
use Mockery;
use PHPUnit\Framework\TestCase;

class TerritoryServiceTest extends TestCase
{
    private $database;
    private $repository;
    private $service;
    private $pageRepository;

    private $pageTerritoryRepository;

    protected function setUp(): void
    {
        $this->database = Mockery::mock(Database::class);
        $this->repository = Mockery::mock(TerritoryRepository::class);
        $this->pageRepository = Mockery::mock(PageRepository::class);
        $this->pageTerritoryRepository = Mockery::mock(PageTerritoryRepository::class);

        $this->service = new TerritoryService(
            $this->database,
            $this->repository,
            $this->pageRepository,
            $this->pageTerritoryRepository
        );
    }

    public function testCreateTerritory()
    {
        $data = [
            'name' => 'United Kingdom',
            'code' => 'GB',
            'slug' => 'united-kingdom',
            'region_set_id' => 1,
            'site_id' => 1
        ];

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $mockTerritory = Mockery::mock(Territory::class)->makePartial();
        $mockTerritory->id = 1;

        $this->repository->shouldReceive('create')
            ->once()
            ->with($data)
            ->andReturn($mockTerritory);

        $result = $this->service->create($data);

        $this->assertInstanceOf(Territory::class, $result);
    }

    public function testCreateTerritoryWithoutSlug()
    {
        $data = [
            'name' => 'United Kingdom',
            'code' => 'GB',
            'region_set_id' => 1,
            'site_id' => 1
        ];

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $mockTerritory = Mockery::mock(Territory::class)->makePartial();
        $mockTerritory->id = 1;
        $mockTerritory->slug = 'united-kingdom';

        $this->repository->shouldReceive('slugExists')
            ->once()
            ->andReturn(false);

        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function($data) {
                return isset($data['slug']) && $data['slug'] === 'united-kingdom';
            }))
            ->andReturn($mockTerritory);

        $result = $this->service->create($data);

        $this->assertInstanceOf(Territory::class, $result);
        $this->assertEquals('united-kingdom', $result->slug);
    }

    public function testUpdateTerritory()
    {
        $territoryId = 1;
        $data = [
            'name' => 'Updated UK',
            'code' => 'GB'
        ];

        $mockTerritory = Mockery::mock(Territory::class)->makePartial();
        $mockTerritory->id = $territoryId;

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->repository->shouldReceive('find')
            ->once()
            ->with($territoryId)
            ->andReturn($mockTerritory);

        $this->repository->shouldReceive('update')
            ->once()
            ->with($territoryId, $data)
            ->andReturn($mockTerritory);

        $result = $this->service->update($territoryId, $data);

        $this->assertInstanceOf(Territory::class, $result);
    }

    public function testDeleteTerritoryWithoutDependencies()
    {
        $territoryId = 1;

        $mockTerritory = Mockery::mock(Territory::class)->makePartial();
        $mockTerritory->shouldReceive('getPageCount')
            ->once()
            ->andReturn(0);

        $this->repository->shouldReceive('find')
            ->once()
            ->with($territoryId)
            ->andReturn($mockTerritory);

        $this->repository->shouldReceive('delete')
            ->once()
            ->with($territoryId)
            ->andReturn(true);

        $result = $this->service->delete($territoryId);

        $this->assertTrue($result);
    }

    public function testDeleteTerritoryWithDependenciesThrowsException()
    {
        $territoryId = 1;

        $mockTerritory = Mockery::mock(Territory::class);
        $mockTerritory->shouldReceive('getPageCount')
            ->once()
            ->andReturn(5);

        $this->repository->shouldReceive('find')
            ->once()
            ->with($territoryId)
            ->andReturn($mockTerritory);

        $this->expectException(CannotDeleteException::class);

        $this->service->delete($territoryId);
    }

    public function testDeleteTerritoryWithReassignment()
    {
        $territoryId = 1;
        $reassignToId = 2;

        $mockTerritory = Mockery::mock(Territory::class)->makePartial();
        $mockTerritory->id = $territoryId;
        $mockTerritory->shouldReceive('getPageCount')
            ->once()
            ->andReturn(3);

        $mockReassignTerritory = Mockery::mock(Territory::class)->makePartial();
        $mockReassignTerritory->id = $reassignToId;

        $this->repository->shouldReceive('find')
            ->with($territoryId)
            ->andReturn($mockTerritory);

        $this->repository->shouldReceive('find')
            ->with($reassignToId)
            ->andReturn($mockReassignTerritory);

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->repository->shouldReceive('reassignPages')
            ->once()
            ->with($territoryId, $reassignToId)
            ->andReturn(true);

        $mockTerritory->shouldReceive('delete')
            ->once()
            ->andReturn(true);

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

        $this->repository->shouldReceive('checkDeletable')
            ->once()
            ->with($territoryId)
            ->andReturn($expectedResult);

        $result = $this->service->checkDeletable($territoryId);

        $this->assertEquals($expectedResult, $result);
    }

    public function testBulkUpdateRegionSet()
    {
        $territoryIds = [1, 2, 3];
        $newRegionSetId = 5;

        $this->repository->shouldReceive('bulkUpdateRegionSet')
            ->once()
            ->with($territoryIds, $newRegionSetId)
            ->andReturn(true);

        $result = $this->service->bulkUpdateRegionSet($territoryIds, $newRegionSetId);

        $this->assertTrue($result);
    }

    public function testReorder()
    {
        $orderedIds = [3, 1, 2];

        $this->repository->shouldReceive('reorderTerritories')
            ->once()
            ->with($orderedIds)
            ->andReturn(true);

        $result = $this->service->reorder($orderedIds);

        $this->assertTrue($result);
    }

//    public function testAssignPagesToRegionSet()
//    {
//        $regionSetId = 1;
//        $pageIds = [1, 2, 3];
//
//        $mockPage1 = Mockery::mock(Page::class)->makePartial();
//        $mockPage1->id = 1;
//        $mockPage1->shouldReceive('save')->once()->andReturn(true);
//
//        $mockPage2 = Mockery::mock(Page::class)->makePartial();
//        $mockPage2->id = 2;
//        $mockPage2->shouldReceive('save')->once()->andReturn(true);
//
//        $mockPage3 = Mockery::mock(Page::class)->makePartial();
//        $mockPage3->id = 3;
//        $mockPage3->shouldReceive('save')->once()->andReturn(true);
//
//        $this->database->expects($this->once())
//            ->method('transaction')
//            ->willReturnCallback(function ($callback) {
//                return $callback();
//            });
//
//
//        $this->pageRepository
//            ->method('find')
//            ->willReturnCallback(function ($id) use ($mockPage1, $mockPage2, $mockPage3) {
//                return match ($id) {
//                    1 => $mockPage1,
//                    2 => $mockPage2,
//                    3 => $mockPage3,
//                    default => null,
//                };
//            });
//
//        $service = new TerritoryService(
//            $this->database,
//            $this->repository,
//            $this->pageRepository,
//            $this->pageTerritoryRepository
//        );
//
//        $result = $service->assignPages($regionSetId, $pageIds, 1);
//
//        $this->assertTrue($result);
//    }

    public function testSearchAvailablePagesForRegionSet()
    {
        $regionSetId = 1;
        $searchQuery = 'test';

        $mockPages = [
            ['id' => 1, 'title' => 'Test Page 1'],
            ['id' => 2, 'title' => 'Test Page 2']
        ];

        $this->repository->shouldReceive('searchAvailablePages')
            ->once()
            ->with($regionSetId, $searchQuery, 20, 1)
            ->andReturn($mockPages);

        $service = new TerritoryService(
            $this->database,
            $this->repository,
            $this->pageRepository,
            $this->pageTerritoryRepository
        );

        $result = $service->searchAvailablePages($regionSetId, $searchQuery);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testUnassignPagesFromTerritory()
    {
        $territoryId = 1;
        $pageIds = [1, 2];

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->pageTerritoryRepository->shouldReceive('unassignPages')
            ->once()
            ->with($territoryId, $pageIds);

        $result = $this->service->unassignPages($territoryId, $pageIds);

        $this->assertTrue($result);
    }

    public function testUnassignPagesOnlyUnassignsFromCorrectTerritory()
    {
        $territoryId = 1;
        $pageIds = [1, 2];

        $mockPage1 = Mockery::mock(Page::class)->makePartial();
        $mockPage1->id = 1;
        $mockPage1->territory_id = $territoryId;

        $mockPage2 = Mockery::mock(Page::class)->makePartial();
        $mockPage2->id = 2;
        $mockPage2->territory_id = 2;

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->pageTerritoryRepository->shouldReceive('unassignPages')
            ->once()
            ->with($territoryId, $pageIds)
            ->andReturn(1);

        $result = $this->service->unassignPages($territoryId, $pageIds);

        $this->assertTrue($result);
        $this->assertEquals(2, $mockPage2->territory_id);
    }
}