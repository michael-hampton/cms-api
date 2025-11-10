<?php

namespace App\Tests\Unit\Services;

use App\Exceptions\CannotDeleteException;
use App\Framework\Database\Database;
use App\Models\Voucher;
use App\Repositories\VoucherRepository;
use App\Services\VoucherService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use PHPUnit\Framework\TestCase;

class VoucherServiceTest extends FunctionalTestCase
{
    private $databaseMock;
    private $repository;
    private $service;

    protected function setUp(): void
    {
        $this->databaseMock = Mockery::mock(Database::class);
        $this->repository = Mockery::mock(VoucherRepository::class);
        $this->service = new VoucherService($this->databaseMock, $this->repository);

        parent::setUp();
    }

    public function testCreateVoucher()
    {
        $data = [
            'code' => 'TEST10',
            'name' => 'Test Voucher',
            'type' => 'percentage',
            'value' => 10,
            'site_id' => 1
        ];

        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->code = 'TEST10';

        $this->repository->shouldReceive('create')
            ->once()
            ->with($data)
            ->andReturn($voucher);

        $result = $this->service->create($data);

        $this->assertInstanceOf(Voucher::class, $result);
        $this->assertEquals('TEST10', $result->code);
    }

    public function testUpdateVoucher()
    {
        $voucherId = 1;
        $data = [
            'name' => 'Updated Voucher',
            'value' => 15
        ];

        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->name = 'Updated Voucher';

        $this->repository->shouldReceive('update')
            ->once()
            ->with($voucherId, $data)
            ->andReturn($voucher);

        $result = $this->service->update($voucherId, $data);

        $this->assertInstanceOf(Voucher::class, $result);
        $this->assertEquals('Updated Voucher', $result->name);
    }

    public function testDeleteVoucherWithNoUsage()
    {
        $voucherId = 1;
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->id = 1;

        $this->repository->shouldReceive('find')
            ->once()
            ->with($voucherId)
            ->andReturn($voucher);

        $this->repository->shouldReceive('delete')
            ->once()
            ->with($voucherId)
            ->andReturn(true);

        $result = $this->service->delete($voucherId);

        $this->assertTrue($result);
    }

    public function testDeleteVoucherWithUsageThrowsException()
    {
        $voucherId = 1;
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->id = 1;
        $voucher->usage_count = 5;

        $this->repository->shouldReceive('find')
            ->once()
            ->with($voucherId)
            ->andReturn($voucher);

        $this->expectException(CannotDeleteException::class);

        $this->service->delete($voucherId);
    }

    public function testDeleteNonExistentVoucherThrowsException()
    {
        $voucherId = 999;

        $this->repository->shouldReceive('find')
            ->once()
            ->with($voucherId)
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Voucher not found');

        $this->service->delete($voucherId);
    }

    public function testCheckDeletable()
    {
        $voucherId = 1;
        $expected = [
            'can_delete' => true,
            'usage_count' => 0,
            'requires_confirmation' => false
        ];

        $this->repository->shouldReceive('checkDeletable')
            ->once()
            ->with($voucherId)
            ->andReturn($expected);

        $result = $this->service->checkDeletable($voucherId);

        $this->assertEquals($expected, $result);
    }

    public function testGetAlternativeVouchers()
    {
        $voucher1 = Mockery::mock(Voucher::class)->makePartial();
        $voucher2 = Mockery::mock(Voucher::class)->makePartial();

        $voucherId = 1;
        $vouchers = collect([
            $voucher1,
            $voucher2
        ]);

        $this->repository->shouldReceive('getAlternatives')
            ->once()
            ->with($voucherId)
            ->andReturn($vouchers);

        $result = $this->service->getAlternativeVouchers($voucherId);

        $this->assertCount(2, $result);
    }

    public function testDuplicateVoucher()
    {
        $voucherId = 1;
        $originalVoucher = Mockery::mock(Voucher::class)->makePartial();
        $originalVoucher->code = 'ORIGINAL';
        $originalVoucher->status = 'inactive';

        $newVoucher = Mockery::mock(Voucher::class)->makePartial();
        $newVoucher->status = 'inactive';

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

        $result = $this->service->duplicateVoucher($voucherId);

        $this->assertInstanceOf(Voucher::class, $result);
        $this->assertEquals('inactive', $result->status);
    }

    private function setupRelationExpectations(Voucher $voucher)
    {
        $voucher->shouldReceive('products')->andReturn(collect());
        $voucher->shouldReceive('categories')->andReturn(collect());
        $voucher->shouldReceive('brands')->andReturn(collect());
    }

    public function testDuplicateVoucherWithCustomCode()
    {
        $voucherId = 1;
        $newCode = 'CUSTOM';

        $originalVoucher = Mockery::mock(Voucher::class)->makePartial();
        $newVoucher = Mockery::mock(Voucher::class)->makePartial();
        $newVoucher->code = $newCode;

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

        $result = $this->service->duplicateVoucher($voucherId, $newCode);

        $this->assertEquals('CUSTOM', $result->code);
    }

    public function testValidateVoucherNotFound()
    {
        $code = 'NOTFOUND';

        $this->repository->shouldReceive('findByCode')
            ->once()
            ->with($code)
            ->andReturn(null);

        $result = $this->service->validateVoucher($code, 100);

        $this->assertFalse($result['valid']);
        $this->assertEquals('Voucher not found', $result['message']);
        $this->assertEquals(0, $result['discount']);
    }

    public function testValidateExpiredVoucher()
    {
        $code = 'EXPIRED';
        $voucher = Mockery::mock(Voucher::class)->makePartial();

        $voucher->status = 'expired';

        $voucher->shouldReceive('isValid')
            ->once()
            ->andReturn(false);

        $this->repository->shouldReceive('findByCode')
            ->once()
            ->with($code)
            ->andReturn($voucher);

        $result = $this->service->validateVoucher($code, 100);

        $this->assertFalse($result['valid']);
        $this->assertEquals('Voucher has expired', $result['message']);
    }

    public function testValidateVoucherBelowMinimumOrder()
    {
        $code = 'TEST10';
        $voucher = Mockery::mock(Voucher::class)->makePartial();

        $voucher->minimum_order_value = 50;
        $voucher->status = 'active';

        $voucher->shouldReceive('isValid')->andReturn(true);

        $this->repository->shouldReceive('findByCode')
            ->once()
            ->with($code)
            ->andReturn($voucher);

        $result = $this->service->validateVoucher($code, 30);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Minimum order value', $result['message']);
    }

    public function testValidateVoucherSuccess()
    {
        $code = 'TEST10';
        $orderValue = 100;
        $expectedDiscount = 10;

        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->id = 1;
        $voucher->minimum_order_value = null;

        $voucher->shouldReceive('isValid')->andReturn(true);
        $voucher->shouldReceive('calculateDiscount')->with($orderValue)->andReturn($expectedDiscount);

        $this->repository->shouldReceive('findByCode')
            ->once()
            ->with($code)
            ->andReturn($voucher);

        $result = $this->service->validateVoucher($code, $orderValue);

        $this->assertTrue($result['valid']);
        $this->assertEquals('Voucher applied successfully', $result['message']);
        $this->assertEquals($expectedDiscount, $result['discount']);
        $this->assertEquals(1, $result['voucher_id']);
    }

    public function testApplyVoucher()
    {
        $voucherId = 1;
        $userId = 123;
        $discountAmount = 15.50;

        $this->repository->shouldReceive('incrementUsageCount')
            ->once()
            ->with($voucherId)
            ->andReturn(true);

        $this->repository->shouldReceive('createRedemption')
            ->once()
            ->with($voucherId, $userId, $discountAmount, null)
            ->andReturn(true);

        $result = $this->service->applyVoucher($voucherId, $userId, $discountAmount);

        $this->assertTrue($result);
    }

    public function testValidateVoucherChecksPerUserLimit()
    {
        $code = 'LIMITED';
        $userId = 123;

        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->per_user_limit = 2;
        $voucher->status = 'active';

        $voucher->shouldReceive('isValid')->andReturn(true);
        $voucher->shouldReceive('getUserUsageCount')->with($userId)->andReturn(2);

        $this->repository->shouldReceive('findByCode')
            ->once()
            ->with($code)
            ->andReturn($voucher);

        $result = $this->service->validateVoucher($code, 100, $userId);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('already used', $result['message']);
    }

    public function testUpdateExpiredVouchers()
    {
        $expectedCount = 3;

        $this->repository->shouldReceive('updateExpiredVouchers')
            ->once()
            ->andReturn($expectedCount);

        $result = $this->service->updateExpiredVouchers();

        $this->assertEquals($expectedCount, $result);
    }

    public function testCreateVoucherWithProducts()
    {
        $data = [
            'code' => 'TEST10',
            'name' => 'Test Voucher',
            'type' => 'percentage',
            'value' => 10,
            'site_id' => 1,
            'product_ids' => [1, 2, 3]
        ];

        $voucher = new Voucher(array_merge($data, ['id' => 1]));

        $this->repository->shouldReceive('create')
            ->once()
            ->andReturn($voucher);

        $this->repository->shouldReceive('syncProducts')
            ->with(1, [1, 2, 3])
            ->once();

        $result = $this->service->create($data);

        $this->assertInstanceOf(Voucher::class, $result);
    }

    public function testUpdateVoucherProducts()
    {
        $voucherId = 1;
        $data = [
            'name' => 'Updated Voucher',
            'product_ids' => [4, 5]
        ];

        $voucher = new Voucher(['id' => $voucherId]);

        $this->repository->shouldReceive('syncProducts')
            ->with(1, [4,5])
            ->once();

        $this->repository->shouldReceive('update')
            ->once()
            ->andReturn($voucher);

        $result = $this->service->update($voucherId, $data);

        $this->assertInstanceOf(Voucher::class, $result);
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

        $result = $this->service->duplicateVoucher($voucherId);

        $this->assertInstanceOf(Voucher::class, $result);
    }

    public function testValidateVoucherForSpecificProduct()
    {
        $code = 'PRODUCT10';
        $productId = 5;
        $orderValue = 100;

        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->id = 1;
        $voucher->minimum_order_value = null;

        $voucher->shouldReceive('isValid')->andReturn(true);
        $voucher->shouldReceive('isApplicableToProduct')->with($productId)->andReturn(true);
        $voucher->shouldReceive('calculateDiscount')->andReturn(10);

        $this->repository->shouldReceive('findByCode')
            ->once()
            ->andReturn($voucher);

        $result = $this->service->validateVoucher($code, $orderValue, null, $productId);

        $this->assertTrue($result['valid']);
    }

    public function testValidateVoucherNotApplicableToProduct()
    {
        $code = 'PRODUCT10';
        $productId = 99;

        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->shouldReceive('isValid')->andReturn(true);
        $voucher->shouldReceive('isApplicableToProduct')->with($productId)->andReturn(false);

        $this->repository->shouldReceive('findByCode')
            ->once()
            ->andReturn($voucher);

        $result = $this->service->validateVoucher($code, 100, null, $productId);

        $this->assertFalse($result['valid']);
        $this->assertEquals('Voucher not applicable to this product', $result['message']);
    }

    public function testCreateVoucherWithCategories()
    {
        $data = [
            'code' => 'TEST10',
            'name' => 'Test Voucher',
            'type' => 'percentage',
            'value' => 10,
            'site_id' => 1,
            'category_ids' => [1, 2, 3]
        ];

        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->id = 1;
        $voucher->code = 'TEST10';

        $this->repository->shouldReceive('create')
            ->once()
            ->andReturn($voucher);

        $this->repository->shouldReceive('syncCategories')
            ->once()
            ->with(1, [1, 2, 3]);

        $result = $this->service->create($data);

        $this->assertInstanceOf(Voucher::class, $result);
    }

    public function testCreateVoucherWithBrands()
    {
        $data = [
            'code' => 'TEST10',
            'name' => 'Test Voucher',
            'type' => 'percentage',
            'value' => 10,
            'site_id' => 1,
            'brand_ids' => [1, 2]
        ];

        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->id = 1;
        $voucher->code = 'TEST10';

        $this->repository->shouldReceive('create')
            ->once()
            ->andReturn($voucher);

        $this->repository->shouldReceive('syncBrands')
            ->once()
            ->with(1, [1, 2]);

        $result = $this->service->create($data);

        $this->assertInstanceOf(Voucher::class, $result);
    }

    public function testUpdateVoucherCategories()
    {
        $voucherId = 1;
        $data = [
            'name' => 'Updated Voucher',
            'category_ids' => [4, 5]
        ];

        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->name = 'Updated Voucher';

        $this->repository->shouldReceive('update')
            ->once()
            ->andReturn($voucher);

        $this->repository->shouldReceive('syncCategories')
            ->once()
            ->with($voucherId, [4, 5]);

        $result = $this->service->update($voucherId, $data);

        $this->assertInstanceOf(Voucher::class, $result);
    }

    public function testUpdateVoucherBrands()
    {
        $voucherId = 1;
        $data = [
            'name' => 'Updated Voucher',
            'brand_ids' => [6, 7]
        ];

        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->name = 'Updated Voucher';

        $this->repository->shouldReceive('update')
            ->once()
            ->andReturn($voucher);

        $this->repository->shouldReceive('syncBrands')
            ->once()
            ->with($voucherId, [6, 7]);

        $result = $this->service->update($voucherId, $data);

        $this->assertInstanceOf(Voucher::class, $result);
    }

    public function testUpdateVoucherDoesNotSyncCategoriesWhenNotProvided()
    {
        $voucherId = 1;
        $data = [
            'name' => 'Updated Voucher',
            'value' => 15
        ];

        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->name = 'Updated Voucher';

        $this->repository->shouldReceive('update')
            ->once()
            ->andReturn($voucher);

        $this->repository->shouldNotReceive('syncCategories');
        $this->repository->shouldNotReceive('syncBrands');

        $result = $this->service->update($voucherId, $data);

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

        $result = $this->service->duplicateVoucher($voucherId);

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

        $result = $this->service->duplicateVoucher($voucherId);

        $this->assertInstanceOf(Voucher::class, $result);
    }

    public function testValidateVoucherApplicableViaCategory()
    {
        $code = 'CATEGORY10';
        $productId = 5;
        $orderValue = 100;

        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->id = 1;
        $voucher->minimum_order_value = null;

        $voucher->shouldReceive('isValid')->andReturn(true);
        $voucher->shouldReceive('isApplicableToProduct')->with($productId)->andReturn(true);
        $voucher->shouldReceive('calculateDiscount')->andReturn(10);

        $this->repository->shouldReceive('findByCode')
            ->once()
            ->andReturn($voucher);

        $result = $this->service->validateVoucher($code, $orderValue, null, $productId);

        $this->assertTrue($result['valid']);
    }

    public function testValidateVoucherApplicableViaBrand()
    {
        $code = 'BRAND10';
        $productId = 5;
        $orderValue = 100;

        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->id = 1;
        $voucher->minimum_order_value = null;

        $voucher->shouldReceive('isValid')->andReturn(true);
        $voucher->shouldReceive('isApplicableToProduct')->with($productId)->andReturn(true);
        $voucher->shouldReceive('calculateDiscount')->andReturn(10);

        $this->repository->shouldReceive('findByCode')
            ->once()
            ->andReturn($voucher);

        $result = $this->service->validateVoucher($code, $orderValue, null, $productId);

        $this->assertTrue($result['valid']);
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

        $result = $this->service->bulkUpdateStatus([1, 2], 'active');

        $this->assertCount(2, $result['updated']);
        $this->assertCount(0, $result['failed']);
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

        $result = $this->service->bulkDelete([1, 2]);

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

        $result = $this->service->bulkDelete([1]);

        $this->assertCount(0, $result['deleted']);
        $this->assertCount(1, $result['failed']);
        $this->assertStringContainsString('used', $result['failed'][0]['reason']);
    }

    public function testApplyVoucherCreatesRedemption()
    {
        $voucherId = 1;
        $userId = 123;
        $discountAmount = 15.50;
        $orderId = 456;

        $this->repository->shouldReceive('incrementUsageCount')
            ->once()
            ->with($voucherId)
            ->andReturn(true);

        $this->repository->shouldReceive('createRedemption')
            ->once()
            ->with($voucherId, $userId, $discountAmount, $orderId)
            ->andReturn(true);

        $result = $this->service->applyVoucher($voucherId, $userId, $discountAmount, $orderId);

        $this->assertTrue($result);
    }

    public function testApplyVoucherWithoutUserOrOrder()
    {
        $voucherId = 1;

        $this->repository->shouldReceive('incrementUsageCount')
            ->once()
            ->with($voucherId)
            ->andReturn(true);

        $this->repository->shouldReceive('createRedemption')
            ->once()
            ->with($voucherId, null, 0.0, null)
            ->andReturn(true);

        $result = $this->service->applyVoucher($voucherId);

        $this->assertTrue($result);
    }

    public function testApplyVoucherDoesNotCreateRedemptionWhenAmountIsZero()
    {
        $voucherId = 1;
        $userId = 123;

        $this->repository->shouldReceive('incrementUsageCount')
            ->once()
            ->with($voucherId)
            ->andReturn(true);

        $this->repository->shouldNotReceive('createRedemption');

        $result = $this->service->applyVoucher($voucherId, $userId, 0);

        $this->assertTrue($result);
    }

    public function testApplyVoucherDoesNotCreateRedemptionWhenIncrementFails()
    {
        $voucherId = 1;
        $userId = 123;
        $discountAmount = 10.00;

        $this->repository->shouldReceive('incrementUsageCount')
            ->once()
            ->with($voucherId)
            ->andReturn(false);

        $this->repository->shouldNotReceive('createRedemption');

        $result = $this->service->applyVoucher($voucherId, $userId, $discountAmount);

        $this->assertFalse($result);
    }

    public function testValidateVoucherAllowsUsageWithinLimit()
    {
        $code = 'LIMITED';
        $userId = 123;

        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->id = 1;
        $voucher->per_user_limit = 3;
        $voucher->status = 'active';
        $voucher->minimum_order_value = null;

        $voucher->shouldReceive('isValid')->andReturn(true);
        $voucher->shouldReceive('getUserUsageCount')->with($userId)->andReturn(1);
        $voucher->shouldReceive('calculateDiscount')->andReturn(10);

        $this->repository->shouldReceive('findByCode')
            ->once()
            ->with($code)
            ->andReturn($voucher);

        $result = $this->service->validateVoucher($code, 100, $userId);

        $this->assertTrue($result['valid']);
    }

    public function testValidateVoucherSkipsPerUserLimitWhenNoUserId()
    {
        $code = 'LIMITED';

        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->id = 1;
        $voucher->per_user_limit = 1;
        $voucher->status = 'active';
        $voucher->minimum_order_value = null;

        $voucher->shouldReceive('isValid')->andReturn(true);
        $voucher->shouldNotReceive('getUserUsageCount');
        $voucher->shouldReceive('calculateDiscount')->andReturn(10);

        $this->repository->shouldReceive('findByCode')
            ->once()
            ->with($code)
            ->andReturn($voucher);

        $result = $this->service->validateVoucher($code, 100, null);

        $this->assertTrue($result['valid']);
    }

    public function testValidateVoucherSkipsPerUserLimitWhenNotSet()
    {
        $code = 'UNLIMITED';
        $userId = 123;

        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->id = 1;
        $voucher->per_user_limit = null;
        $voucher->status = 'active';
        $voucher->minimum_order_value = null;

        $voucher->shouldReceive('isValid')->andReturn(true);
        $voucher->shouldNotReceive('getUserUsageCount');
        $voucher->shouldReceive('calculateDiscount')->andReturn(10);

        $this->repository->shouldReceive('findByCode')
            ->once()
            ->with($code)
            ->andReturn($voucher);

        $result = $this->service->validateVoucher($code, 100, $userId);

        $this->assertTrue($result['valid']);
    }
}