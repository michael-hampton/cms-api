<?php

namespace App\Tests\Unit\Actions;

use App\Actions\BulkActivateTerritories;
use App\Framework\Database\Database;
use App\Models\Territory;
use App\Repositories\Cms\TerritoryRepository;
use Mockery;
use PHPUnit\Framework\TestCase;

class BulkActivateTerritoriesActionTest extends TestCase
{
    private $database;
    private $repository;
    private $service;

    protected function setUp(): void
    {
        $this->database = Mockery::mock(Database::class);
        $this->repository = Mockery::mock(TerritoryRepository::class);

        $this->service = new BulkActivateTerritories(
            $this->database,
            $this->repository,
        );
    }

    public function testBulkActivateSuccessfully()
    {
        $territory1 = Mockery::mock(Territory::class)->makePartial();
        $territory2 = Mockery::mock(Territory::class)->makePartial();

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

        $this->repository->shouldReceive('update')
            ->twice()
            ->andReturn($territory1, $territory2);

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