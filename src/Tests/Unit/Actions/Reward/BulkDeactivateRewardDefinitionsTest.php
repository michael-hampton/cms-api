<?php

namespace App\Tests\Unit\Actions\Reward;

use App\Actions\Reward\BulkDeactivateRewardDefinitions;
use App\Framework\Database\Database;
use App\Models\RewardDefinition;
use App\Repositories\Rewards\RewardDefinitionRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class BulkDeactivateRewardDefinitionsTest extends FunctionalTestCase
{
    private $databaseMock;
    private $repository;
    private $service;

    public function testBulkDeactivateSuccessfully()
    {
        $rewardDef1 = Mockery::mock(RewardDefinition::class)->makePartial();
        $rewardDef1->id = 1;
        $rewardDef1->is_active = true;

        $rewardDef2 = Mockery::mock(RewardDefinition::class)->makePartial();
        $rewardDef2->id = 2;
        $rewardDef2->is_active = true;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository->shouldReceive('find')->with(1)->andReturn($rewardDef1);
        $this->repository->shouldReceive('find')->with(2)->andReturn($rewardDef2);

        $this->repository->shouldReceive('update')
            ->twice()
            ->andReturn($rewardDef1, $rewardDef2);

        $results = $this->service->handle([1, 2]);

        $this->assertCount(2, $results['updated']);
        $this->assertCount(0, $results['failed']);
        $this->assertEquals(2, $results['total']);
    }

    public function testBulkDeactivateHandlesNotFound()
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository->shouldReceive('find')->with(999)->andReturn(null);

        $results = $this->service->handle([999]);

        $this->assertCount(0, $results['updated']);
        $this->assertCount(1, $results['failed']);
        $this->assertEquals('Reward definition not found', $results['failed'][0]['reason']);
    }

    public function testBulkDeactivateHandlesUpdateFailure()
    {
        $rewardDef = Mockery::mock(RewardDefinition::class)->makePartial();
        $rewardDef->id = 1;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository->shouldReceive('find')->with(1)->andReturn($rewardDef);
        $this->repository->shouldReceive('update')->once()->andReturn(null);

        $results = $this->service->handle([1]);

        $this->assertCount(0, $results['updated']);
        $this->assertCount(1, $results['failed']);
        $this->assertEquals('Update failed', $results['failed'][0]['reason']);
    }

    public function testBulkDeactivateHandlesException()
    {
        $rewardDef = Mockery::mock(RewardDefinition::class)->makePartial();
        $rewardDef->id = 1;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository->shouldReceive('find')->with(1)->andReturn($rewardDef);
        $this->repository->shouldReceive('update')
            ->once()
            ->andThrow(new \Exception('Database error'));

        $results = $this->service->handle([1]);

        $this->assertCount(0, $results['updated']);
        $this->assertCount(1, $results['failed']);
        $this->assertEquals('Database error', $results['failed'][0]['reason']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->databaseMock = Mockery::mock(Database::class);
        $this->repository = Mockery::mock(RewardDefinitionRepository::class);

        $this->service = new BulkDeactivateRewardDefinitions(
            $this->databaseMock,
            $this->repository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}