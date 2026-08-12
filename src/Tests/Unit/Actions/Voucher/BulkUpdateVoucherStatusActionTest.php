<?php

namespace App\Tests\Unit\Actions\Voucher;

use App\Actions\Voucher\BulkUpdateVoucherStatus;
use App\Framework\Database\Database;
use App\Models\Voucher;
use App\Repositories\Vouchers\VoucherRepository;
use App\Tests\Unit\UnitTestCase;
use App\Tests\Unit\Services\Concerns\HasSiteHistory;
use Mockery;

class BulkUpdateVoucherStatusActionTest extends UnitTestCase
{
    use HasSiteHistory;

    private $databaseMock;
    private $repository;
    private $service;

    protected function setUp(): void
    {

        $this->databaseMock = Mockery::mock(Database::class);
        $this->repository = Mockery::mock(VoucherRepository::class);
        $this->service = new BulkUpdateVoucherStatus($this->databaseMock, $this->repository);
    }

    public function testBulkUpdateStatusSuccessfully(): void
    {
        $voucher1 = Mockery::mock(Voucher::class)->makePartial();
        $voucher2 = Mockery::mock(Voucher::class)->makePartial();

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

        $this->repository->shouldReceive('update')
            ->twice()
            ->andReturn($voucher1, $voucher2);

        $result = $this->service->handle([1, 2], 'active');

        $this->assertCount(2, $result['updated']);
        $this->assertCount(0, $result['failed']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}