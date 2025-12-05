<?php

namespace App\Tests\Unit\Actions;

use App\Actions\CloneVoucher;
use App\Framework\Database\Database;
use App\Models\Voucher;
use App\Repositories\VoucherRepository;
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

        $this->assertInstanceOf(Voucher::class, $result['voucher']);
        $this->assertEquals('inactive', $result['voucher']->status);;
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

        $this->assertEquals('CUSTOM', $result['voucher']->code);
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

        $this->assertInstanceOf(Voucher::class, $result['voucher']);
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

        $this->assertInstanceOf(Voucher::class, $result['voucher']);
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

        $this->assertInstanceOf(Voucher::class, $result['voucher']);
    }

    public function testDuplicateVoucherReturnsDetailedResults()
    {
        $originalVoucher = Mockery::mock(Voucher::class)->makePartial();
        $originalVoucher->id = 1;
        $originalVoucher->code = 'ORIGINAL';

        $newVoucher = Mockery::mock(Voucher::class)->makePartial();
        $newVoucher->id = 2;

        $this->setCloneHistoryExpectations($originalVoucher, $newVoucher, 1, 2);

        $this->repository->shouldReceive('find')->with(1)->andReturn($originalVoucher);
        $this->repository->shouldReceive('findByCode')->andReturn(null);
        $this->setupRelationExpectations($originalVoucher);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository->shouldReceive('create')->andReturn($newVoucher);

        $result = $this->service->handle(1);

        $this->assertArrayHasKey('results', $result);
        $this->assertContains('voucher_created', $result['results']['success']);
        $this->assertContains('clone_history', $result['results']['success']);
        $this->assertEquals(0, $result['results']['products_associated']);
        $this->assertEquals(0, $result['results']['categories_associated']);
        $this->assertEquals(0, $result['results']['brands_associated']);
        $this->assertEquals(1, $result['original_voucher_id']);
    }

    public function testDuplicateVoucherWithAllAssociations()
    {
        $originalVoucher = Mockery::mock(Voucher::class)->makePartial();
        $originalVoucher->id = 1;
        $originalVoucher->code = 'ORIGINAL';

        $originalVoucher->shouldReceive('products->pluck->toArray')->andReturn([1, 2, 3]);
        $originalVoucher->shouldReceive('categories->pluck->toArray')->andReturn([10, 11]);
        $originalVoucher->shouldReceive('brands->pluck->toArray')->andReturn([20]);

        $newVoucher = Mockery::mock(Voucher::class)->makePartial();
        $newVoucher->id = 2;

        $this->setCloneHistoryExpectations($originalVoucher, $newVoucher, 1, 2);

        $newVoucher->shouldReceive('products->sync')->once()->with([1, 2, 3]);
        $newVoucher->shouldReceive('categories->sync')->once()->with([10, 11]);
        $newVoucher->shouldReceive('brands->sync')->once()->with([20]);

        $this->repository->shouldReceive('find')->andReturn($originalVoucher);
        $this->repository->shouldReceive('findByCode')->andReturn(null);
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());
        $this->repository->shouldReceive('create')->andReturn($newVoucher);

        $result = $this->service->handle(1);

        $this->assertEquals(3, $result['results']['products_associated']);
        $this->assertEquals(2, $result['results']['categories_associated']);
        $this->assertEquals(1, $result['results']['brands_associated']);
        $this->assertContains('products_associated', $result['results']['success']);
        $this->assertContains('categories_associated', $result['results']['success']);
        $this->assertContains('brands_associated', $result['results']['success']);
    }

    public function testDuplicateVoucherThrowsExceptionWhenNotFound()
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository->shouldReceive('find')->with(999)->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Voucher not found');

        $this->service->handle(999);
    }

    public function testDuplicateVoucherHandlesProductAssociationFailure()
    {
        $originalVoucher = Mockery::mock(Voucher::class)->makePartial();
        $originalVoucher->id = 1;
        $originalVoucher->code = 'ORIGINAL';

        $originalVoucher->shouldReceive('products->pluck->toArray')
            ->andThrow(new \Exception('Database error'));
        $originalVoucher->shouldReceive('categories->pluck->toArray')->andReturn([]);
        $originalVoucher->shouldReceive('brands->pluck->toArray')->andReturn([]);

        $newVoucher = Mockery::mock(Voucher::class)->makePartial();
        $newVoucher->id = 2;

        $this->setCloneHistoryExpectations($originalVoucher, $newVoucher, 1, 2);

        $this->repository->shouldReceive('find')->andReturn($originalVoucher);
        $this->repository->shouldReceive('findByCode')->andReturn(null);
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());
        $this->repository->shouldReceive('create')->andReturn($newVoucher);

        $result = $this->service->handle(1);

        $this->assertCount(1, $result['results']['failed']);
        $this->assertEquals('associate_products', $result['results']['failed'][0]['operation']);
        $this->assertEquals(0, $result['results']['products_associated']);
    }

    public function testDuplicateVoucherHandlesCategoryAssociationFailure()
    {
        $originalVoucher = Mockery::mock(Voucher::class)->makePartial();
        $originalVoucher->id = 1;
        $originalVoucher->code = 'ORIGINAL';

        $originalVoucher->shouldReceive('products->pluck->toArray')->andReturn([]);
        $originalVoucher->shouldReceive('categories->pluck->toArray')
            ->andThrow(new \Exception('Category sync failed'));
        $originalVoucher->shouldReceive('brands->pluck->toArray')->andReturn([]);

        $newVoucher = Mockery::mock(Voucher::class)->makePartial();
        $newVoucher->id = 2;

        $this->setCloneHistoryExpectations($originalVoucher, $newVoucher, 1, 2);

        $this->repository->shouldReceive('find')->andReturn($originalVoucher);
        $this->repository->shouldReceive('findByCode')->andReturn(null);
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());
        $this->repository->shouldReceive('create')->andReturn($newVoucher);

        $result = $this->service->handle(1);

        $this->assertCount(1, $result['results']['failed']);
        $this->assertEquals('associate_categories', $result['results']['failed'][0]['operation']);
    }

    public function testDuplicateVoucherHandlesBrandAssociationFailure()
    {
        $originalVoucher = Mockery::mock(Voucher::class)->makePartial();
        $originalVoucher->id = 1;
        $originalVoucher->code = 'ORIGINAL';

        $originalVoucher->shouldReceive('products->pluck->toArray')->andReturn([]);
        $originalVoucher->shouldReceive('categories->pluck->toArray')->andReturn([]);
        $originalVoucher->shouldReceive('brands->pluck->toArray')
            ->andThrow(new \Exception('Brand sync failed'));

        $newVoucher = Mockery::mock(Voucher::class)->makePartial();
        $newVoucher->id = 2;

        $this->setCloneHistoryExpectations($originalVoucher, $newVoucher, 1, 2);

        $this->repository->shouldReceive('find')->andReturn($originalVoucher);
        $this->repository->shouldReceive('findByCode')->andReturn(null);
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());
        $this->repository->shouldReceive('create')->andReturn($newVoucher);

        $result = $this->service->handle(1);

        $this->assertCount(1, $result['results']['failed']);
        $this->assertEquals('associate_brands', $result['results']['failed'][0]['operation']);
    }

    public function testDuplicateVoucherGeneratesUniqueCode()
    {
        $originalVoucher = Mockery::mock(Voucher::class)->makePartial();
        $originalVoucher->id = 1;
        $originalVoucher->code = 'SUMMER';

        $newVoucher = Mockery::mock(Voucher::class)->makePartial();
        $newVoucher->id = 2;

        $this->setCloneHistoryExpectations($originalVoucher, $newVoucher, 1, 2);

        $this->repository->shouldReceive('find')->andReturn($originalVoucher);

        // First code exists
        $existingVoucher = new Voucher(['id' => 99]);
        $this->repository->shouldReceive('findByCode')->with('SUMMER')->once()->andReturn($existingVoucher);

        // Second code also exists
        $this->repository->shouldReceive('findByCode')->with('SUMMER1')->once()->andReturn($existingVoucher);

        // Third code is available
        $this->repository->shouldReceive('findByCode')->with('SUMMER2')->once()->andReturn(null);

        $this->setupRelationExpectations($originalVoucher);
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['code'] === 'SUMMER2';
            }))
            ->andReturn($newVoucher);

        $result = $this->service->handle(1);

        $this->assertInstanceOf(Voucher::class, $result['voucher']);
    }

    public function testDuplicateVoucherResetsUsageCount()
    {
        $originalVoucher = Mockery::mock(Voucher::class)->makePartial();
        $originalVoucher->id = 1;
        $originalVoucher->code = 'ORIGINAL';
        $originalVoucher->usage_count = 50;

        $newVoucher = Mockery::mock(Voucher::class)->makePartial();
        $newVoucher->id = 2;

        $this->setCloneHistoryExpectations($originalVoucher, $newVoucher, 1, 2);

        $this->repository->shouldReceive('find')->andReturn($originalVoucher);
        $this->repository->shouldReceive('findByCode')->andReturn(null);
        $this->setupRelationExpectations($originalVoucher);
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['usage_count'] === 0;
            }))
            ->andReturn($newVoucher);

        $result = $this->service->handle(1);

        $this->assertInstanceOf(Voucher::class, $result['voucher']);
    }

    public function testDuplicateVoucherSetsStatusToInactive()
    {
        $originalVoucher = Mockery::mock(Voucher::class)->makePartial();
        $originalVoucher->id = 1;
        $originalVoucher->code = 'ORIGINAL';
        $originalVoucher->status = 'active';

        $newVoucher = Mockery::mock(Voucher::class)->makePartial();
        $newVoucher->id = 2;

        $this->setCloneHistoryExpectations($originalVoucher, $newVoucher, 1, 2);

        $this->repository->shouldReceive('find')->andReturn($originalVoucher);
        $this->repository->shouldReceive('findByCode')->andReturn(null);
        $this->setupRelationExpectations($originalVoucher);
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['status'] === 'inactive';
            }))
            ->andReturn($newVoucher);

        $result = $this->service->handle(1);

        $this->assertInstanceOf(Voucher::class, $result['voucher']);
    }

    public function testDuplicateVoucherWithPartialAssociationFailures()
    {
        $originalVoucher = Mockery::mock(Voucher::class)->makePartial();
        $originalVoucher->id = 1;
        $originalVoucher->code = 'ORIGINAL';

        $originalVoucher->shouldReceive('products->pluck->toArray')->andReturn([1, 2]);
        $originalVoucher->shouldReceive('categories->pluck->toArray')
            ->andThrow(new \Exception('Category failed'));
        $originalVoucher->shouldReceive('brands->pluck->toArray')->andReturn([5]);

        $newVoucher = Mockery::mock(Voucher::class)->makePartial();
        $newVoucher->id = 2;

        $this->setCloneHistoryExpectations($originalVoucher, $newVoucher, 1, 2);

        $newVoucher->shouldReceive('products->sync')->once()->with([1, 2]);
        $newVoucher->shouldReceive('brands->sync')->once()->with([5]);

        $this->repository->shouldReceive('find')->andReturn($originalVoucher);
        $this->repository->shouldReceive('findByCode')->andReturn(null);
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());
        $this->repository->shouldReceive('create')->andReturn($newVoucher);

        $result = $this->service->handle(1);

        $this->assertEquals(2, $result['results']['products_associated']);
        $this->assertEquals(1, $result['results']['brands_associated']);
        $this->assertEquals(0, $result['results']['categories_associated']);
        $this->assertCount(1, $result['results']['failed']);
        $this->assertEquals('associate_categories', $result['results']['failed'][0]['operation']);
    }

    private function setupRelationExpectations(Voucher $voucher)
    {
        $voucher->shouldReceive('products')->andReturn(collect());
        $voucher->shouldReceive('categories')->andReturn(collect());
        $voucher->shouldReceive('brands')->andReturn(collect());
    }
}