<?php

namespace App\Tests\Unit\Actions\Reward;

use App\Actions\Reward\BulkUpdateMemberRewardStatus;
use App\Framework\Database\Database;
use App\Models\MemberReward;
use App\Repositories\Rewards\RewardsRepository;
use App\Tests\Unit\UnitTestCase;
use Mockery;

class BulkUpdateMemberRewardStatusTest extends UnitTestCase
{
    private $databaseMock;
    private $repository;
    private $service;

    public function testBulkUpdateStatusSuccessfully()
    {
        $reward1 = Mockery::mock(MemberReward::class)->makePartial();
        $reward1->id = 1;
        $reward1->status = 'pending';
        $reward1->claimed_at = null;

        $reward2 = Mockery::mock(MemberReward::class)->makePartial();
        $reward2->id = 2;
        $reward2->status = 'pending';
        $reward2->claimed_at = null;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository->shouldReceive('find')->with(1)->andReturn($reward1);
        $this->repository->shouldReceive('find')->with(2)->andReturn($reward2);

        $this->repository->shouldReceive('update')
            ->twice()
            ->andReturn($reward1, $reward2);

        $results = $this->service->handle([1, 2], 'claimed');

        $this->assertCount(2, $results['updated']);
        $this->assertCount(0, $results['failed']);
        $this->assertEquals(2, $results['total']);
    }

    public function testBulkUpdateStatusThrowsExceptionForInvalidStatus()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid status value');

        $this->service->handle([1], 'invalid-status');
    }

    public function testBulkUpdateStatusHandlesNotFound()
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository->shouldReceive('find')->with(999)->andReturn(null);

        $results = $this->service->handle([999], 'claimed');

        $this->assertCount(0, $results['updated']);
        $this->assertCount(1, $results['failed']);
        $this->assertEquals('Member reward not found', $results['failed'][0]['reason']);
    }

    public function testBulkUpdateStatusSetsClaimedTimestamp()
    {
        $reward = Mockery::mock(MemberReward::class)->makePartial();
        $reward->id = 1;
        $reward->status = 'pending';
        $reward->claimed_at = null;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository->shouldReceive('find')->with(1)->andReturn($reward);

        $this->repository->shouldReceive('update')
            ->once()
            ->with(1, Mockery::on(function ($data) {
                return $data['status'] === 'claimed' && isset($data['claimed_at']);
            }))
            ->andReturn($reward);

        $results = $this->service->handle([1], 'claimed');

        $this->assertCount(1, $results['updated']);
    }

    public function testBulkUpdateStatusSetsDeclinedTimestamp()
    {
        $reward = Mockery::mock(MemberReward::class)->makePartial();
        $reward->id = 1;
        $reward->status = 'pending';
        $reward->declined_at = null;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository->shouldReceive('find')->with(1)->andReturn($reward);

        $this->repository->shouldReceive('update')
            ->once()
            ->with(1, Mockery::on(function ($data) {
                return $data['status'] === 'declined' && isset($data['declined_at']);
            }))
            ->andReturn($reward);

        $results = $this->service->handle([1], 'declined');

        $this->assertCount(1, $results['updated']);
    }

    public function testBulkUpdateStatusDoesNotOverwriteExistingTimestamp()
    {
        $reward = Mockery::mock(MemberReward::class)->makePartial();
        $reward->id = 1;
        $reward->status = 'pending';
        $reward->claimed_at = '2025-01-01 00:00:00';

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository->shouldReceive('find')->with(1)->andReturn($reward);

        $this->repository->shouldReceive('update')
            ->once()
            ->with(1, Mockery::on(function ($data) {
                return $data['status'] === 'claimed' && !isset($data['claimed_at']);
            }))
            ->andReturn($reward);

        $results = $this->service->handle([1], 'claimed');

        $this->assertCount(1, $results['updated']);
    }

    public function testBulkUpdateStatusHandlesUpdateFailure()
    {
        $reward = Mockery::mock(MemberReward::class)->makePartial();
        $reward->id = 1;
        $reward->status = 'pending';

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository->shouldReceive('find')->with(1)->andReturn($reward);
        $this->repository->shouldReceive('update')->once()->andReturn(null);

        $results = $this->service->handle([1], 'claimed');

        $this->assertCount(0, $results['updated']);
        $this->assertCount(1, $results['failed']);
        $this->assertEquals('Update failed', $results['failed'][0]['reason']);
    }

    public function testBulkUpdateStatusHandlesException()
    {
        $reward = Mockery::mock(MemberReward::class)->makePartial();
        $reward->id = 1;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository->shouldReceive('find')->with(1)->andReturn($reward);
        $this->repository->shouldReceive('update')
            ->once()
            ->andThrow(new \Exception('Database error'));

        $results = $this->service->handle([1], 'claimed');

        $this->assertCount(0, $results['updated']);
        $this->assertCount(1, $results['failed']);
        $this->assertEquals('Database error', $results['failed'][0]['reason']);
    }

    protected function setUp(): void
    {

        $this->databaseMock = Mockery::mock(Database::class);
        $this->repository = Mockery::mock(RewardsRepository::class);

        $this->service = new BulkUpdateMemberRewardStatus(
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