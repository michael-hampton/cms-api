<?php

namespace App\Tests\Unit\Actions\Reward;

use App\Actions\Reward\BulkUpdateRewardStatus;
use App\Framework\Database\Database;
use App\Models\MemberReward;
use App\Repositories\Rewards\RewardsRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class BulkUpdateRewardStatusTest extends FunctionalTestCase
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
    }

    public function testBulkUpdateStatusThrowsExceptionForInvalidStatus()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid status value');

        $this->service->handle([1], 'invalid-status');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->databaseMock = Mockery::mock(Database::class);
        $this->repository = Mockery::mock(RewardsRepository::class);

        $this->service = new BulkUpdateRewardStatus(
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