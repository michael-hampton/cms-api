<?php

namespace App\Tests\Unit\Actions;

use App\Actions\CloneVoucher;
use App\Framework\Database\Database;
use App\Models\Voucher;
use App\Repositories\VoucherRepository;
use App\Services\VoucherService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Services\Concerns\HasSiteHistory;
use Mockery;

class CloneVoucherActionTest extends FunctionalTestCase
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
        $this->service = new CloneVoucher($this->databaseMock, $this->repository);
    }

    protected function tearDown(): void
    {
        // Close all Mockery mocks
        Mockery::close();

        parent::tearDown();
    }

    public function testDuplicateVoucher()
    {
        $voucherId = 1;
        $originalVoucher = Mockery::mock(Voucher::class)->makePartial();
        $originalVoucher->id = $voucherId;
        $originalVoucher->code = 'ORIGINAL';
        $originalVoucher->status = 'inactive';

        $newVoucher = Mockery::mock(Voucher::class)->makePartial();
        $newVoucher->id = 2;
        $newVoucher->status = 'inactive';

        $this->setCloneHistoryExpectations($originalVoucher, $newVoucher, 1, 2);

        $this->repository->shouldReceive('find')
            ->once()
            ->with($voucherId)
            ->andReturn($originalVoucher);

        $this->repository->shouldReceive('findByCode')
            ->once()
            ->with('ORIGINAL')
            ->andReturn(null);

        $this->setupRelationExpectations($originalVoucher);


        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function($callback) {
                return $callback();
            });

        $this->repository->shouldReceive('create')
            ->once()
            ->andReturn($newVoucher);

        $result = $this->service->handle($voucherId);

        $this->assertInstanceOf(Voucher::class, $result);
        $this->assertEquals('inactive', $result->status);
    }

    public function testDuplicateVoucherWithCustomCode()
    {
        $voucherId = 1;
        $newCode = 'CUSTOM';

        $originalVoucher = Mockery::mock(Voucher::class)->makePartial();
        $originalVoucher->id = $voucherId;
        $newVoucher = Mockery::mock(Voucher::class)->makePartial();
        $newVoucher->code = $newCode;
        $newVoucher->id = 2;

        $this->setCloneHistoryExpectations($originalVoucher, $newVoucher, 1, 2);

        $this->repository->shouldReceive('find')
            ->once()
            ->with($voucherId)
            ->andReturn($originalVoucher);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function($callback) {
                return $callback();
            });

        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function($data) use ($newCode) {
                return $data['code'] === strtoupper($newCode);
            }))
            ->andReturn($newVoucher);

        $this->setupRelationExpectations($originalVoucher);

        $result = $this->service->handle($voucherId, $newCode);

        $this->assertEquals('CUSTOM', $result->code);
    }

    public function testDuplicateVoucherCopiesProductAssociations()
    {
        $voucherId = 1;
        $originalVoucher = Mockery::mock(Voucher::class)->makePartial();
        $originalVoucher->id = $voucherId;
        $originalVoucher->code = 'ORIGINAL';
        $originalVoucher->name = 'Original';
        $originalVoucher->type = 'percentage';
        $originalVoucher->value = 10;
        $originalVoucher->site_id = 1;

        $products = collect([
            (object)['id' => 1],
            (object)['id' => 2]
        ]);

        $originalVoucher->shouldReceive('categories')->andReturn(collect([]));
        $originalVoucher->shouldReceive('brands')->andReturn(collect([]));


        $originalVoucher->shouldReceive('products->pluck->toArray')
            ->andReturn([1, 2]);

        $newVoucher = Mockery::mock(Voucher::class)->makePartial();
        $newVoucher->id = 2;

        $this->setCloneHistoryExpectations($originalVoucher, $newVoucher, 1, 2);

        $newVoucher->shouldReceive('products->sync')
            ->once()
            ->with([1, 2]);

        $this->repository->shouldReceive('find')
            ->once()
            ->andReturn($originalVoucher);

        $this->repository->shouldReceive('findByCode')
            ->once()
            ->andReturn(null);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function($callback) {
                return $callback();
            });

        $this->repository->shouldReceive('create')
            ->once()
            ->andReturn($newVoucher);

        $result = $this->service->handle($voucherId);

        $this->assertInstanceOf(Voucher::class, $result);
    }

    public function testDuplicateVoucherCopiesBrandAssociations()
    {
        $voucherId = 1;
        $originalVoucher = Mockery::mock(Voucher::class)->makePartial();
        $originalVoucher->id = $voucherId;
        $originalVoucher->code = 'ORIGINAL';
        $originalVoucher->name = 'Original';
        $originalVoucher->type = 'percentage';
        $originalVoucher->value = 10;
        $originalVoucher->site_id = 1;

        $originalVoucher->shouldReceive('products->pluck->toArray')
            ->andReturn([]);

        $originalVoucher->shouldReceive('categories->pluck->toArray')
            ->andReturn([]);

        $originalVoucher->shouldReceive('brands->pluck->toArray')
            ->andReturn([5, 6]);

        $newVoucher = Mockery::mock(Voucher::class)->makePartial();
        $newVoucher->id = 2;

        $this->setCloneHistoryExpectations($originalVoucher, $newVoucher, 1, 2);

        $newVoucher->shouldReceive('brands->sync')
            ->once()
            ->with([5, 6]);

        $this->repository->shouldReceive('find')
            ->once()
            ->andReturn($originalVoucher);

        $this->repository->shouldReceive('findByCode')
            ->once()
            ->andReturn(null);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function($callback) {
                return $callback();
            });

        $this->repository->shouldReceive('create')
            ->once()
            ->andReturn($newVoucher);

        $result = $this->service->handle($voucherId);

        $this->assertInstanceOf(Voucher::class, $result);
    }

    public function testDuplicateVoucherCopiesCategoryAssociations()
    {
        $voucherId = 1;
        $originalVoucher = Mockery::mock(Voucher::class)->makePartial();
        $originalVoucher->id = $voucherId;
        $originalVoucher->code = 'ORIGINAL';
        $originalVoucher->name = 'Original';
        $originalVoucher->type = 'percentage';
        $originalVoucher->value = 10;
        $originalVoucher->site_id = 1;

        $originalVoucher->shouldReceive('products->pluck->toArray')
            ->andReturn([]);

        $originalVoucher->shouldReceive('categories->pluck->toArray')
            ->andReturn([1, 2]);

        $originalVoucher->shouldReceive('brands->pluck->toArray')
            ->andReturn([]);

        $newVoucher = Mockery::mock(Voucher::class)->makePartial();
        $newVoucher->id = 2;

        $this->setCloneHistoryExpectations($originalVoucher, $newVoucher, 1, 2);

        $newVoucher->shouldReceive('categories->sync')
            ->once()
            ->with([1, 2]);

        $this->repository->shouldReceive('find')
            ->once()
            ->andReturn($originalVoucher);

        $this->repository->shouldReceive('findByCode')
            ->once()
            ->andReturn(null);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function($callback) {
                return $callback();
            });

        $this->repository->shouldReceive('create')
            ->once()
            ->andReturn($newVoucher);

        $result = $this->service->handle($voucherId);

        $this->assertInstanceOf(Voucher::class, $result);
    }

    private function setupRelationExpectations(Voucher $voucher)
    {
        $voucher->shouldReceive('products')->andReturn(collect());
        $voucher->shouldReceive('categories')->andReturn(collect());
        $voucher->shouldReceive('brands')->andReturn(collect());
    }
}