<?php

namespace App\Tests\Unit\Actions\Reward;

use App\Actions\Reward\BulkCloneRewards;
use App\Framework\Database\Database;
use App\Models\RewardDefinition;
use App\Repositories\Rewards\RewardDefinitionRepository;
use App\Services\Cms\ImageUploadService;
use App\Tests\Unit\UnitTestCase;
use Mockery;

class BulkCloneRewardsTest extends UnitTestCase
{
    private $databaseMock;
    private $repository;
    private $imageUploadService;
    private $service;

    public function testBulkCloneRewardsSuccessfully()
    {
        $reward1 = Mockery::mock(RewardDefinition::class)->makePartial();
        $reward1->id = 1;
        $reward1->name = 'Reward 1';
        $reward1->site_id = 1;
        $reward1->reward_type = 'badge';
        $reward1->shouldReceive('addCloneRecord')->once();

        $reward2 = Mockery::mock(RewardDefinition::class)->makePartial();
        $reward2->id = 2;
        $reward2->name = 'Reward 2';
        $reward2->site_id = 1;
        $reward2->reward_type = 'voucher';
        $reward2->shouldReceive('addCloneRecord')->once();

        $newReward1 = Mockery::mock(RewardDefinition::class)->makePartial();
        $newReward1->id = 3;
        $newReward1->shouldReceive('addCloneRecord')->once();

        $newReward2 = Mockery::mock(RewardDefinition::class)->makePartial();
        $newReward2->id = 4;
        $newReward2->shouldReceive('addCloneRecord')->once();

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository->shouldReceive('find')->with(1)->andReturn($reward1);
        $this->repository->shouldReceive('find')->with(2)->andReturn($reward2);

        $this->repository->shouldReceive('findBySlug')
            ->andReturn(null);

        $this->repository->shouldReceive('create')
            ->twice()
            ->andReturn($newReward1, $newReward2);

        $results = $this->service->handle([1, 2]);

        $this->assertTrue($results[1]['success']);
        $this->assertTrue($results[2]['success']);
        $this->assertEquals(3, $results[1]['cloned_reward_id']);
        $this->assertEquals(4, $results[2]['cloned_reward_id']);
    }

    public function testBulkCloneRewardsHandlesNotFound()
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository->shouldReceive('find')->with(999)->andReturn(null);

        $results = $this->service->handle([999]);

        $this->assertFalse($results[999]['success']);
        $this->assertEquals('Reward not found', $results[999]['error']);
    }

    protected function setUp(): void
    {

        $this->databaseMock = Mockery::mock(Database::class);
        $this->repository = Mockery::mock(RewardDefinitionRepository::class);
        $this->imageUploadService = Mockery::mock(ImageUploadService::class);

        $this->service = new BulkCloneRewards(
            $this->databaseMock,
            $this->repository,
            $this->imageUploadService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}