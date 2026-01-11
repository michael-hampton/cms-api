<?php

namespace App\Tests\Unit\Actions;

use App\Actions\BulkDeleteVoucher;
use App\Framework\Database\Database;
use App\Models\Voucher;
use App\Repositories\Cms\VoucherRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Services\Concerns\HasSiteHistory;
use Mockery;

class BulkDeleteVoucherActionTest extends FunctionalTestCase
{
    use HasSiteHistory;

    private $databaseMock;
    private $repository;
    private $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databaseMock = Mockery::mock(Database::class);
        $this->repository = Mockery::mock(VoucherRepository::class);
        $this->service = new BulkDeleteVoucher($this->databaseMock, $this->repository);
    }
    public function testBulkDeleteSuccessfully(): void
    {
        $voucher1 = Mockery::mock(Voucher::class)->makePartial();
        $voucher1->usage_count = 0;

        $voucher2 = Mockery::mock(Voucher::class)->makePartial();
        $voucher2->usage_count = 0;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($voucher1);

        $this->repository->shouldReceive('find')
            ->with(2)
            ->once()
            ->andReturn($voucher2);

        $this->repository->shouldReceive('delete')
            ->twice()
            ->andReturn(true);

        $result = $this->service->handle([1, 2]);

        $this->assertCount(2, $result['deleted']);
        $this->assertCount(0, $result['failed']);
    }

    public function testBulkDeleteFailsWhenUsageCountExists(): void
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->usage_count = 5;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($voucher);

        $result = $this->service->handle([1]);

        $this->assertCount(0, $result['deleted']);
        $this->assertCount(1, $result['failed']);
        $this->assertStringContainsString('used', $result['failed'][0]['reason']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}