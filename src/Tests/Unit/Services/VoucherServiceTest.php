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
        $this->databaseMock = $this->createMock(Database::class);
        $this->repository = $this->createMock(VoucherRepository::class);
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

        $voucher = new Voucher($data);

        $this->repository->expects($this->once())
            ->method('create')
            ->with($data)
            ->willReturn($voucher);

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

        $voucher = new Voucher(array_merge(['id' => $voucherId], $data));

        $this->repository->expects($this->once())
            ->method('update')
            ->with($voucherId, $data)
            ->willReturn($voucher);

        $result = $this->service->update($voucherId, $data);

        $this->assertInstanceOf(Voucher::class, $result);
        $this->assertEquals('Updated Voucher', $result->name);
    }

    public function testDeleteVoucherWithNoUsage()
    {
        $voucherId = 1;
        $voucher = new Voucher([
            'id' => $voucherId,
            'code' => 'TEST10',
            'usage_count' => 0
        ]);

        $this->repository->expects($this->once())
            ->method('find')
            ->with($voucherId)
            ->willReturn($voucher);

        $this->repository->expects($this->once())
            ->method('delete')
            ->with($voucherId)
            ->willReturn(true);

        $result = $this->service->delete($voucherId);

        $this->assertTrue($result);
    }

    public function testDeleteVoucherWithUsageThrowsException()
    {
        $voucherId = 1;
        $voucher = new Voucher([
            'id' => $voucherId,
            'code' => 'TEST10',
            'usage_count' => 5
        ]);

        $this->repository->expects($this->once())
            ->method('find')
            ->with($voucherId)
            ->willReturn($voucher);

        $this->expectException(CannotDeleteException::class);

        $this->service->delete($voucherId);
    }

    public function testDeleteNonExistentVoucherThrowsException()
    {
        $voucherId = 999;

        $this->repository->expects($this->once())
            ->method('find')
            ->with($voucherId)
            ->willReturn(null);

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

        $this->repository->expects($this->once())
            ->method('checkDeletable')
            ->with($voucherId)
            ->willReturn($expected);

        $result = $this->service->checkDeletable($voucherId);

        $this->assertEquals($expected, $result);
    }

    public function testGetAlternativeVouchers()
    {
        $voucherId = 1;
        $vouchers = collect([
            new Voucher(['id' => 2, 'code' => 'ALT1']),
            new Voucher(['id' => 3, 'code' => 'ALT2'])
        ]);

        $this->repository->expects($this->once())
            ->method('getAlternatives')
            ->with($voucherId)
            ->willReturn($vouchers);

        $result = $this->service->getAlternativeVouchers($voucherId);

        $this->assertCount(2, $result);
    }

    public function testDuplicateVoucher()
    {
        $voucherId = 1;
        $originalVoucher = new Voucher([
            'id' => $voucherId,
            'code' => 'ORIGINAL',
            'name' => 'Original Voucher',
            'type' => 'percentage',
            'value' => 10,
            'site_id' => 1
        ]);

        $newVoucher = new Voucher([
            'id' => 2,
            'code' => 'ORIGINAL1',
            'name' => 'Original Voucher (Copy)',
            'type' => 'percentage',
            'value' => 10,
            'site_id' => 1,
            'status' => 'inactive'
        ]);

        $this->repository->expects($this->once())
            ->method('find')
            ->with($voucherId)
            ->willReturn($originalVoucher);

        $this->repository->expects($this->once())
            ->method('findByCode')
            ->with('ORIGINAL')
            ->willReturn(null);

        $this->databaseMock->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(function($callback) {
                return $callback();
            });

        $this->repository->expects($this->once())
            ->method('create')
            ->willReturn($newVoucher);

        $result = $this->service->duplicateVoucher($voucherId);

        $this->assertInstanceOf(Voucher::class, $result);
        $this->assertEquals('inactive', $result->status);
    }

    public function testDuplicateVoucherWithCustomCode()
    {
        $voucherId = 1;
        $newCode = 'CUSTOM';
        $originalVoucher = new Voucher([
            'id' => $voucherId,
            'code' => 'ORIGINAL',
            'name' => 'Original Voucher',
            'type' => 'percentage',
            'value' => 10,
            'site_id' => 1
        ]);

        $newVoucher = new Voucher([
            'id' => 2,
            'code' => 'CUSTOM',
            'name' => 'Original Voucher (Copy)',
            'type' => 'percentage',
            'value' => 10,
            'site_id' => 1
        ]);

        $this->repository->expects($this->once())
            ->method('find')
            ->with($voucherId)
            ->willReturn($originalVoucher);

        $this->databaseMock->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(function($callback) {
                return $callback();
            });

        $this->repository->expects($this->once())
            ->method('create')
            ->with($this->callback(function($data) use ($newCode) {
                return $data['code'] === strtoupper($newCode);
            }))
            ->willReturn($newVoucher);

        $result = $this->service->duplicateVoucher($voucherId, $newCode);

        $this->assertEquals('CUSTOM', $result->code);
    }

    public function testValidateVoucherNotFound()
    {
        $code = 'NOTFOUND';

        $this->repository->expects($this->once())
            ->method('findByCode')
            ->with($code)
            ->willReturn(null);

        $result = $this->service->validateVoucher($code, 100);

        $this->assertFalse($result['valid']);
        $this->assertEquals('Voucher not found', $result['message']);
        $this->assertEquals(0, $result['discount']);
    }

    public function testValidateExpiredVoucher()
    {
        $code = 'EXPIRED';
        $voucher = $this->createMock(Voucher::class);

        $voucher->status = 'expired';

        $voucher->expects($this->once())
            ->method('isValid')
            ->willReturn(false);

        $this->repository->expects($this->once())
            ->method('findByCode')
            ->with($code)
            ->willReturn($voucher);

        $result = $this->service->validateVoucher($code, 100);

        $this->assertFalse($result['valid']);
        $this->assertEquals('Voucher is not valid', $result['message']);
    }

    public function testValidateVoucherBelowMinimumOrder()
    {
        $code = 'TEST10';
        $voucher = Mockery::mock(Voucher::class)->makePartial();

        $voucher->minimum_order_value = 50;
        $voucher->status = 'active';

        $voucher->shouldReceive('isValid')->andReturn(true);

        $this->repository->expects($this->once())
            ->method('findByCode')
            ->with($code)
            ->willReturn($voucher);

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

        $this->repository->expects($this->once())
            ->method('findByCode')
            ->with($code)
            ->willReturn($voucher);

        $result = $this->service->validateVoucher($code, $orderValue);

        $this->assertTrue($result['valid']);
        $this->assertEquals('Voucher applied successfully', $result['message']);
        $this->assertEquals($expectedDiscount, $result['discount']);
        $this->assertEquals(1, $result['voucher_id']);
    }

    public function testApplyVoucher()
    {
        $voucherId = 1;

        $this->repository->expects($this->once())
            ->method('incrementUsageCount')
            ->with($voucherId)
            ->willReturn(true);

        $result = $this->service->applyVoucher($voucherId);

        $this->assertTrue($result);
    }

    public function testUpdateExpiredVouchers()
    {
        $expectedCount = 3;

        $this->repository->expects($this->once())
            ->method('updateExpiredVouchers')
            ->willReturn($expectedCount);

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

        $this->repository->expects($this->once())
            ->method('create')
            ->willReturn($voucher);

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

        $this->repository->expects($this->once())
            ->method('update')
            ->willReturn($voucher);

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

        $originalVoucher->shouldReceive('products->pluck->toArray')
            ->andReturn([1, 2]);

        $newVoucher = Mockery::mock(Voucher::class)->makePartial();
        $newVoucher->id = 2;

        $newVoucher->shouldReceive('products->sync')
            ->once()
            ->with([1, 2]);

        $this->repository->expects($this->once())
            ->method('find')
            ->willReturn($originalVoucher);

        $this->repository->expects($this->once())
            ->method('findByCode')
            ->willReturn(null);

        $this->databaseMock->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(function($callback) {
                return $callback();
            });

        $this->repository->expects($this->once())
            ->method('create')
            ->willReturn($newVoucher);

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

        $this->repository->expects($this->once())
            ->method('findByCode')
            ->willReturn($voucher);

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

        $this->repository->expects($this->once())
            ->method('findByCode')
            ->willReturn($voucher);

        $result = $this->service->validateVoucher($code, 100, null, $productId);

        $this->assertFalse($result['valid']);
        $this->assertEquals('Voucher not applicable to this product', $result['message']);
    }
}