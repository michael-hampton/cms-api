<?php

namespace App\Tests\Unit\Actions\Reward;

use App\Actions\Reward\BulkDeclineRewards;
use App\Framework\Database\Database;
use App\Models\MemberReward;
use App\Repositories\Rewards\RewardsRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class BulkDeclineRewardsTest extends FunctionalTestCase
{
    private $databaseMock;
    private $repository;
    private $service;

    public function testBulkDeclineRewardsSuccessfully()
    {
        $reward1 = Mockery::mock(MemberReward::class)->makePartial();
        $reward1->id = 1;
        $reward1->status = 'pending';

        $reward2 = Mockery::mock(MemberReward::class)->makePartial();
        $reward2->id = 2;
        $reward2->status = 'pending';

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository->shouldReceive('find')->with(1)->andReturn($reward1);
        $this->repository->shouldReceive('find')->with(2)->andReturn($reward2);

        $this->repository->shouldReceive('update')
            ->twice()
            ->andReturn($reward1, $reward2);

        $results = $this->service->handle([1, 2], 'Test reason');

        $this->assertCount(2, $results['updated']);
        $this->assertCount(0, $results['failed']);
        $this->assertEquals(2, $results['total']);
    }

    public function testBulkDeclineRewardsHandlesInvalidStatus()
    {
        $reward = Mockery::mock(MemberReward::class)->makePartial();
        $reward->id = 1;
        $reward->status = 'claimed';

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository->shouldReceive('find')->with(1)->andReturn($reward);

        $results = $this->service->handle([1]);

        $this->assertCount(0, $results['updated']);
        $this->assertCount(1, $results['failed']);
        $this->assertEquals('Can only decline pending rewards', $results['failed'][0]['reason']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->databaseMock = Mockery::mock(Database::class);
        $this->repository = Mockery::mock(RewardsRepository::class);

        $this->service = new BulkDeclineRewards(
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