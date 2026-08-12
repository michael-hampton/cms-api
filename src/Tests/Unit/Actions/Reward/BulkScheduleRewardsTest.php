<?php

namespace App\Tests\Unit\Actions\Reward;

use App\Actions\Reward\BulkScheduleRewards;
use App\Framework\Database\Database;
use App\Models\MemberReward;
use App\Repositories\Rewards\RewardsRepository;
use App\Tests\Unit\UnitTestCase;
use Mockery;

class BulkScheduleRewardsTest extends UnitTestCase
{
    private $databaseMock;
    private $repository;
    private $service;

    public function testBulkScheduleSuccessfully()
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

        $schedules = [
            ['reward_id' => 1, 'expires_at' => '2025-12-31 23:59:59'],
            ['reward_id' => 2, 'expires_at' => '2025-11-30 23:59:59']
        ];

        $results = $this->service->handle($schedules);

        $this->assertTrue($results[1]['success']);
        $this->assertTrue($results[2]['success']);
        $this->assertEquals('2025-12-31 23:59:59', $results[1]['expires_at']);
        $this->assertEquals('2025-11-30 23:59:59', $results[2]['expires_at']);
    }

    public function testBulkScheduleFailsForNonPendingRewards()
    {
        $reward = Mockery::mock(MemberReward::class)->makePartial();
        $reward->id = 1;
        $reward->status = 'claimed';

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository->shouldReceive('find')->with(1)->andReturn($reward);

        $schedules = [
            ['reward_id' => 1, 'expires_at' => '2025-12-31 23:59:59']
        ];

        $results = $this->service->handle($schedules);

        $this->assertFalse($results[1]['success']);
        $this->assertEquals('Can only schedule pending rewards', $results[1]['error']);
    }

    protected function setUp(): void
    {

        $this->databaseMock = Mockery::mock(Database::class);
        $this->repository = Mockery::mock(RewardsRepository::class);

        $this->service = new BulkScheduleRewards(
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