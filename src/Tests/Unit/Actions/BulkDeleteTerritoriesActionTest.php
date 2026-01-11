<?php

namespace App\Tests\Unit\Actions;

use App\Actions\BulkDeleteTerritories;
use App\Framework\Database\Database;
use App\Models\Territory;
use App\Repositories\Cms\PageRepository;
use App\Repositories\Cms\PageTerritoryRepository;
use App\Repositories\Cms\TerritoryRepository;
use Mockery;
use PHPUnit\Framework\TestCase;

class BulkDeleteTerritoriesActionTest extends TestCase
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

        $this->service = new BulkDeleteTerritories(
            $this->database,
            $this->repository,
            $this->pageRepository,
            $this->pageTerritoryRepository
        );
    }

    public function testBulkDeleteSuccessfully()
    {
        $territory1 = Mockery::mock(Territory::class)->makePartial();
        $territory1->shouldReceive('getPageCount')->once()->andReturn(0);

        $territory2 = Mockery::mock(Territory::class)->makePartial();
        $territory2->shouldReceive('getPageCount')->once()->andReturn(0);

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($territory1);

        $this->repository->shouldReceive('find')
            ->with(2)
            ->once()
            ->andReturn($territory2);

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