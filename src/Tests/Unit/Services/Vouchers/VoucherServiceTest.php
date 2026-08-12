<?php

namespace App\Tests\Unit\Services\Vouchers;

use App\DTO\Vouchers\VoucherValidationContext;
use App\DTO\Vouchers\VoucherValidationResult;
use App\Enums\Subscriptions\SubscriptionType;
use App\Enums\Vouchers\VoucherType;
use App\Exceptions\Vouchers\VoucherNotDeletableException;
use App\Exceptions\Vouchers\VoucherNotFoundException;
use App\Framework\Database\Database;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPricing;
use App\Models\Voucher;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Vouchers\VoucherRepository;
use App\Services\Vouchers\VoucherService;
use App\Services\Vouchers\VoucherValidationService;
use App\Tests\Unit\UnitTestCase;
use App\Tests\Unit\Services\Concerns\HasSiteHistory;
use Mockery;

class VoucherServiceTest extends UnitTestCase
{
    use HasSiteHistory;

    private $databaseMock;
    private $repository;
    private $subscriptionPlanRepository;
    private $validationService;
    private $service;

    protected function setUp(): void
    {

        $this->databaseMock = Mockery::mock(Database::class);
        $this->repository = Mockery::mock(VoucherRepository::class);
        $this->subscriptionPlanRepository = Mockery::mock(SubscriptionPlanRepository::class);
        $this->validationService = Mockery::mock(VoucherValidationService::class);

        $this->service = new VoucherService(
            $this->databaseMock,
            $this->repository,
            $this->subscriptionPlanRepository,
            $this->validationService
        );
    }

    protected function tearDown(): void
    {
        // Close all Mockery mocks
        Mockery::close();

        parent::tearDown();
    }

    public function testCreateVoucher()
    {
        $data = [
            'code' => 'TEST10',
            'name' => 'Test Voucher',
            'type' => VoucherType::Percentage->value,
            'value' => 10,
            'site_id' => 1
        ];

        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->id = 1;
        $voucher->code = 'TEST10';

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) use ($voucher) {
                return $callback();
            });

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

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) use ($voucher) {
                return $callback();
            });

        $this->repository->shouldReceive('find')
            ->once()
            ->with($voucherId)
            ->andReturn(new Voucher());

        $this->repository->shouldReceive('update')
            ->once()
            ->with($voucherId, $data)
            ->andReturn($voucher);

        $result = $this->service->update($voucherId, $data);

        $this->assertInstanceOf(Voucher::class, $result);
        $this->assertEquals('Updated Voucher', $result->name);
    }

    public function testUpdateVoucherInvalidatesSyncedStripeCouponWhenSubscriptionConfigChanges(): void
    {
        $voucherId = 1;
        $existingVoucher = new Voucher();
        $existingVoucher->stripe_coupon_id = 'coupon_123';
        $existingVoucher->code = 'SAVE15';
        $existingVoucher->name = 'Save 15';
        $existingVoucher->applies_to_subscriptions = true;
        $existingVoucher->discount_type = VoucherType::Fixed->value;
        $existingVoucher->discount_amount = 1500;
        $existingVoucher->discount_percentage = null;
        $existingVoucher->subscription_discount_duration = 'repeating';
        $existingVoucher->subscription_duration_months = 1;
        $existingVoucher->type = VoucherType::Fixed->value;
        $existingVoucher->value = 15;

        $updatedVoucher = new Voucher();
        $updatedVoucher->stripe_coupon_id = null;

        $data = [
            'discount_amount' => 2000,
        ];

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository->shouldReceive('find')
            ->once()
            ->with($voucherId)
            ->andReturn($existingVoucher);

        $this->repository->shouldReceive('update')
            ->once()
            ->with($voucherId, Mockery::on(function (array $payload) {
                return $payload['discount_amount'] === 2000
                    && array_key_exists('stripe_coupon_id', $payload)
                    && $payload['stripe_coupon_id'] === null
                    && array_key_exists('stripe_coupon_synced_at', $payload)
                    && $payload['stripe_coupon_synced_at'] === null;
            }))
            ->andReturn($updatedVoucher);

        $result = $this->service->update($voucherId, $data);

        $this->assertInstanceOf(Voucher::class, $result);
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

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) use ($voucher) {
                return $callback();
            });

        $this->repository->shouldReceive('delete')
            ->once()
            ->with($voucherId)
            ->andReturn(true);

        $result = $this->service->delete($voucherId);

        $this->assertTrue($result);
    }

    public function testDeleteVoucherThrowsExceptionWhenUsed()
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->id = 1;
        $voucher->usage_count = 5;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->repository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($voucher);

        $this->expectException(VoucherNotDeletableException::class);

        $this->service->delete(1);
    }

    public function testDeleteNonExistentVoucherThrowsException()
    {
        $voucherId = 999;

        $this->repository->shouldReceive('find')
            ->once()
            ->with($voucherId)
            ->andReturn(null);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Voucher not found');

        $this->service->delete($voucherId);
    }

    public function testDeleteThrowsVoucherNotFoundExceptionWhenMissing(): void
    {
        $this->databaseMock->expects('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->repository->expects('find')->with(99)->andReturn(null);

        $this->expectException(VoucherNotFoundException::class);

        $this->service->delete(99);
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

    public function testValidateVoucherNotFound()
    {
        $code = 'NOTFOUND';

        $this->repository->shouldReceive('findByCode')
            ->once()
            ->with($code)
            ->andReturn(null);

        $result = $this->service->validateVoucher($code, 100);

        $this->assertFalse($result->valid);
        $this->assertEquals('Voucher not found', $result->message);
        $this->assertEquals(0, $result->voucher->discount);
    }

    public function testValidateExpiredVoucher()
    {
        $code = 'EXPIRED';
        $voucher = Mockery::mock(Voucher::class)->makePartial();

        $voucher->status = 'expired';

        $this->repository->shouldReceive('findByCode')
            ->once()
            ->with($code)
            ->andReturn($voucher);

        $this->validationService->shouldReceive('validate')
            ->once()
            ->andReturn(VoucherValidationResult::invalid('Voucher has expired'));

        $result = $this->service->validateVoucher($code, 100);

        $this->assertFalse($result->valid);
        $this->assertEquals('Voucher has expired', $result->message);
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

        $this->validationService->shouldReceive('validate')
            ->once()
            ->andReturn(VoucherValidationResult::invalid('Minimum order value not met'));

        $result = $this->service->validateVoucher($code, 30);

        $this->assertFalse($result->valid);
        $this->assertStringContainsString('Minimum order value', $result->message);
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

        $this->validationService->shouldReceive('validate')
            ->once()
            ->with(
                $voucher,
                Mockery::type(VoucherValidationContext::class)
            )
            ->andReturn(VoucherValidationResult::valid($voucher, 10, 10, 10));

        $this->repository->shouldReceive('findByCode')
            ->once()
            ->with($code)
            ->andReturn($voucher);

        $result = $this->service->validateVoucher($code, $orderValue);

        $this->assertTrue($result->valid);
        $this->assertEquals('Voucher applied successfully', $result->message);
        $this->assertEquals($expectedDiscount, $result->discount);
        $this->assertEquals(1, $result->voucher->id);
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

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

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

        $this->validationService->shouldReceive('validate')
            ->once()
            ->andReturn(VoucherValidationResult::invalid('You have already used this voucher the maximum number of times'));

        $this->repository->shouldReceive('findByCode')
            ->once()
            ->with($code)
            ->andReturn($voucher);

        $result = $this->service->validateVoucher($code, 100, $userId);

        $this->assertFalse($result->valid);
        $this->assertStringContainsString('already used', $result->message);
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

    public function testGetVoucherByIdReturnsVoucher(): void
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();

        $this->repository->expects('find')->with(3)->andReturn($voucher);

        $result = $this->service->getVoucherById(3);

        $this->assertSame($voucher, $result);
    }

    public function testGetVoucherByIdReturnsNullWhenNotFound(): void
    {
        $this->repository->expects('find')->with(0)->andReturn(null);

        $result = $this->service->getVoucherById(0);

        $this->assertNull($result);
    }

    public function testCreateVoucherWithProducts()
    {
        $data = [
            'code' => 'TEST10',
            'name' => 'Test Voucher',
            'type' => VoucherType::Percentage->value,
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

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

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

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->repository->shouldNotReceive('find');
        $this->repository->shouldReceive('update')
            ->once()
            ->andReturn($voucher);

        $result = $this->service->update($voucherId, $data);

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

        $this->validationService->shouldReceive('validate')
            ->once()
            ->andReturn(VoucherValidationResult::valid($voucher, 10, 10, 10));

        $result = $this->service->validateVoucher($code, $orderValue, null, $productId);

        $this->assertTrue($result->valid);
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

        $this->validationService->shouldReceive('validate')
            ->once()
            ->andReturn(VoucherValidationResult::invalid('Voucher not applicable to this product'));

        $result = $this->service->validateVoucher($code, 100, null, $productId);

        $this->assertFalse($result->valid);
        $this->assertEquals('Voucher not applicable to this product', $result->message);
    }

    public function testCreateVoucherWithCategories()
    {
        $data = [
            'code' => 'TEST10',
            'name' => 'Test Voucher',
            'type' => VoucherType::Percentage->value,
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

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

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
            'type' => VoucherType::Percentage->value,
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

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->repository->shouldReceive('syncBrands')
            ->once()
            ->with(1, [1, 2]);

        $result = $this->service->create($data);

        $this->assertInstanceOf(Voucher::class, $result);
    }

    public function testCreateDoesNotSyncRelationsWhenNotProvided(): void
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->id = 1;

        $this->databaseMock->expects('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->repository->expects('create')->andReturn($voucher);
        $this->repository->expects('syncProducts')->never();
        $this->repository->expects('syncCategories')->never();
        $this->repository->expects('syncBrands')->never();

        $result = $this->service->create(['code' => 'CLEAN']);

        $this->assertSame($voucher, $result);
    }

    public function testCreateStripsRelationKeysFromMainPayload(): void
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->id = 1;

        $this->databaseMock->expects('transaction')->andReturnUsing(fn($cb) => $cb());

        $this->repository
            ->expects('create')
            ->withArgs(function (array $data) {
                return !array_key_exists('product_ids', $data)
                    && !array_key_exists('category_ids', $data)
                    && !array_key_exists('brand_ids', $data);
            })
            ->andReturn($voucher);

        $this->repository->allows('syncProducts');
        $this->repository->allows('syncCategories');
        $this->repository->allows('syncBrands');

        $result = $this->service->create([
            'code' => 'X',
            'product_ids' => [1],
            'category_ids' => [2],
            'brand_ids' => [3],
        ]);

        $this->assertSame($voucher, $result);
    }

    public function testUpdateSyncsRelationsWhenProvided(): void
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->id = 1;

        $this->databaseMock->expects('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->repository->expects('find')->never();
        $this->repository->expects('update')->andReturn($voucher);
        $this->repository->expects('syncProducts')->with(1, [7])->once();
        $this->repository->expects('syncCategories')->with(1, [8])->once();
        $this->repository->expects('syncBrands')->with(1, [9])->once();

        $result = $this->service->update(1, [
            'code' => 'X',
            'product_ids' => [7],
            'category_ids' => [8],
            'brand_ids' => [9],
        ]);

        $this->assertSame($voucher, $result);
    }

    public function testUpdateDoesNotSyncRelationsWhenNotProvided(): void
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();

        $this->databaseMock->expects('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->repository->expects('find')->never();
        $this->repository->expects('update')->andReturn($voucher);
        $this->repository->expects('syncProducts')->never();
        $this->repository->expects('syncCategories')->never();
        $this->repository->expects('syncBrands')->never();

        $result = $this->service->update(1, ['code' => 'Y']);

        $this->assertSame($voucher, $result);
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

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->repository->shouldNotReceive('find');
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

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->repository->shouldNotReceive('find');
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
        $existingVoucher = new Voucher();
        $existingVoucher->stripe_coupon_id = null;

        $this->repository->shouldReceive('update')
            ->once()
            ->andReturn($voucher);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->repository->shouldReceive('find')
            ->once()
            ->with($voucherId)
            ->andReturn($existingVoucher);
        $this->repository->shouldNotReceive('syncCategories');
        $this->repository->shouldNotReceive('syncBrands');

        $result = $this->service->update($voucherId, $data);

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

        $this->validationService->shouldReceive('validate')
            ->once()
            ->andReturn(VoucherValidationResult::valid($voucher, 10, 10, 10));

        $result = $this->service->validateVoucher($code, $orderValue, null, $productId);

        $this->assertTrue($result->valid);
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

        $this->validationService->shouldReceive('validate')
            ->once()
            ->andReturn(VoucherValidationResult::valid($voucher, 10, 10, 10));

        $this->repository->shouldReceive('findByCode')
            ->once()
            ->andReturn($voucher);

        $result = $this->service->validateVoucher($code, $orderValue, null, $productId);

        $this->assertTrue($result->valid);
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

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

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

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->repository->shouldNotReceive('createRedemption');

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

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

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

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

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

        $this->validationService->shouldReceive('validate')
            ->once()
            ->andReturn(VoucherValidationResult::valid($voucher, 10, 10, 10));

        $this->repository->shouldReceive('findByCode')
            ->once()
            ->with($code)
            ->andReturn($voucher);

        $result = $this->service->validateVoucher($code, 100, $userId);

        $this->assertTrue($result->valid);
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

        $this->validationService->shouldReceive('validate')
            ->once()
            ->andReturn(VoucherValidationResult::valid($voucher, 10, 10, 10));

        $this->repository->shouldReceive('findByCode')
            ->once()
            ->with($code)
            ->andReturn($voucher);

        $result = $this->service->validateVoucher($code, 100, null);

        $this->assertTrue($result->valid);
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

        $this->validationService->shouldReceive('validate')
            ->once()
            ->andReturn(VoucherValidationResult::valid($voucher, 10, 10, 10));

        $result = $this->service->validateVoucher($code, 100, $userId);

        $this->assertTrue($result->valid);
    }

    public function testValidateVoucherForSubscriptionSuccess()
    {
        $code = 'SUB10';
        $planId = 1;
        $userId = 123;
        $subscriptionPrice = 29.99;

        $plan = Mockery::mock(\App\Models\SubscriptionPlan::class)->makePartial();
        $plan->id = $planId;
        $plan->price = $subscriptionPrice;

        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->id = 1;
        $voucher->status = 'active';
        $voucher->per_user_limit = null;

        $this->subscriptionPlanRepository->shouldReceive('find')
            ->once()
            ->with(1, ['pricingTiers'])
            ->andReturn($plan);

        $voucher->shouldReceive('isValid')->andReturn(true);
        $voucher->shouldReceive('appliesToSubscriptions')->andReturn(true);
        $voucher->shouldReceive('isApplicableToSubscriptionPlan')->with($planId)->andReturn(true);
        $voucher->shouldReceive('calculateSubscriptionDiscount')->with($subscriptionPrice)->andReturn(2.99);

        $this->validationService->shouldReceive('validate')
            ->once()
            ->andReturn(VoucherValidationResult::valid($voucher, 2.99, 10, 10));

        $this->repository->shouldReceive('findByCode')
            ->once()
            ->with($code)
            ->andReturn($voucher);

        // Mock the SubscriptionPlanRepository
        $this->subscriptionPlanRepository->shouldReceive('find')->with($planId)->andReturn($plan);

        $result = $this->service->validateVoucherForSubscription($code, $planId, $userId);

        $this->assertTrue($result->valid);
        $this->assertEquals('Voucher applied successfully', $result->message);
        $this->assertEquals(2.99, $result->discount);
        $this->assertEquals(1, $result->voucher->id);
    }

    public function testValidateVoucherForSubscriptionNotFound()
    {
        $this->repository->shouldReceive('findByCode')
            ->once()
            ->with('NOTFOUND')
            ->andReturn(null);

        $result = $this->service->validateVoucherForSubscription('NOTFOUND', 1);

        $this->assertFalse($result->valid);
        $this->assertEquals('Voucher not found', $result->message);
        $this->assertEquals(0, $result->discount);
    }

    public function testValidateVoucherForSubscriptionUsesBasePlanPriceWhenNoTier(): void
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $validResult = Mockery::mock(VoucherValidationResult::class)->makePartial();

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->price = 29.99;
        $plan->pricingTiers = collect([]);

        $this->repository->expects('findByCode')->andReturn($voucher);
        $this->subscriptionPlanRepository->expects('find')->andReturn($plan);

        $this->validationService
            ->expects('validate')
            ->withArgs(function (Voucher $v, VoucherValidationContext $ctx) {
                return $ctx->orderValue == 29.99;
            })
            ->andReturn($validResult);

        $result = $this->service->validateVoucherForSubscription('CODE', 1);

        $this->assertSame($validResult, $result);
    }

    public function testValidateVoucherForSubscriptionReturnsInvalidWhenTierNotFound(): void
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->price = 29.99;
        $plan->pricingTiers = collect([]);

        $this->repository->expects('findByCode')->andReturn($voucher);
        $this->subscriptionPlanRepository->expects('find')->andReturn($plan);

        $result = $this->service->validateVoucherForSubscription('CODE', 1, null, 999);

        $this->assertFalse($result->valid);
        $this->assertSame('Invalid pricing tier', $result->message);
    }

    public function testValidateVoucherForSubscriptionUsesDigitalPriceForDigitalType(): void
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $validResult = Mockery::mock(VoucherValidationResult::class)->makePartial();

        $tier = Mockery::mock(SubscriptionPlanPricing::class)->makePartial();
        $tier->id = 1;
        $tier->digital_price = 15.00;
        $tier->digital_sale_price = 12.00;
        $tier->price = 20.00;
        $tier->sale_price = null;

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->price = 25.00;
        $plan->pricingTiers = collect([$tier]);

        $this->repository->expects('findByCode')->andReturn($voucher);
        $this->subscriptionPlanRepository->expects('find')->andReturn($plan);

        $this->validationService
            ->expects('validate')
            ->withArgs(function (Voucher $v, VoucherValidationContext $ctx) {
                return $ctx->orderValue == 12;
            })
            ->andReturn($validResult);

        $result = $this->service->validateVoucherForSubscription(
            'CODE', 1, null, 1, SubscriptionType::DIGITAL->value
        );

        $this->assertSame($validResult, $result);
    }

    public function testValidateVoucherForSubscriptionUsesPhysicalPriceWhenNotDigital(): void
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $validResult = Mockery::mock(VoucherValidationResult::class)->makePartial();

        $tier = Mockery::mock(SubscriptionPlanPricing::class)->makePartial();
        $tier->id = 1;
        $tier->digital_price = 15.00;
        $tier->digital_sale_price = null;
        $tier->price = 20.00;
        $tier->sale_price = 18.00;

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->price = 25.00;
        $plan->pricingTiers = collect([$tier]);

        $this->repository->expects('findByCode')->andReturn($voucher);
        $this->subscriptionPlanRepository->expects('find')->andReturn($plan);

        $this->validationService
            ->expects('validate')
            ->withArgs(function (Voucher $v, VoucherValidationContext $ctx) {
                return $ctx->orderValue == 18;
            })
            ->andReturn($validResult);

        $result = $this->service->validateVoucherForSubscription('CODE', 1, null, 1, 'physical');

        $this->assertSame($validResult, $result);
    }

    public function testValidateVoucherForSubscriptionNotApplicableToSubscriptions()
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->shouldReceive('isValid')->andReturn(true);
        $voucher->shouldReceive('appliesToSubscriptions')->andReturn(false);

        $this->repository->shouldReceive('findByCode')
            ->once()
            ->andReturn($voucher);

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->price = 10.99;

        $this->subscriptionPlanRepository->shouldReceive('find')->once()->andReturn($plan);
        $this->validationService->shouldReceive('validate')
            ->once()
            ->andReturn(VoucherValidationResult::invalid('This voucher cannot be used for subscriptions'));

        $result = $this->service->validateVoucherForSubscription('TEST', 1);

        $this->assertFalse($result->valid);
        $this->assertEquals('This voucher cannot be used for subscriptions', $result->message);
    }

    public function testValidateVoucherForSubscriptionNotApplicableToPlan()
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->shouldReceive('isValid')->andReturn(true);
        $voucher->shouldReceive('appliesToSubscriptions')->andReturn(true);
        $voucher->shouldReceive('isApplicableToSubscriptionPlan')->with(1)->andReturn(false);

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->price = 10.99;

        $this->repository->shouldReceive('findByCode')
            ->once()
            ->andReturn($voucher);

        $this->subscriptionPlanRepository->shouldReceive('find')->once()->andReturn($plan);
        $this->validationService->shouldReceive('validate')
            ->once()
            ->andReturn(VoucherValidationResult::invalid('Voucher not applicable to this subscription plan'));

        $result = $this->service->validateVoucherForSubscription('TEST', 1);

        $this->assertFalse($result->valid);
        $this->assertEquals('Voucher not applicable to this subscription plan', $result->message);
    }

    public function testValidateVoucherForSubscriptionExceedsPerUserLimit()
    {
        $userId = 123;
        $planId = 1;

        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->per_user_limit = 2;

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->price = 10.99;

        $voucher->shouldReceive('isValid')->andReturn(true);
        $voucher->shouldReceive('appliesToSubscriptions')->andReturn(true);
        $voucher->shouldReceive('isApplicableToSubscriptionPlan')->andReturn(true);
        $voucher->shouldReceive('getUserUsageCount')->with($userId)->andReturn(2);

        $this->repository->shouldReceive('findByCode')
            ->once()
            ->andReturn($voucher);

        $this->subscriptionPlanRepository->shouldReceive('find')->once()->andReturn($plan);
        $this->validationService->shouldReceive('validate')
            ->once()
            ->andReturn(VoucherValidationResult::invalid('You have already used this voucher the maximum number of times'));

        $result = $this->service->validateVoucherForSubscription('TEST', $planId, $userId);

        $this->assertFalse($result->valid);
        $this->assertStringContainsString('already used', $result->message);
    }

    public function testValidateVoucherForSubscriptionPlanNotFound()
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->shouldReceive('isValid')->andReturn(true);
        $voucher->shouldReceive('appliesToSubscriptions')->andReturn(true);
        $voucher->shouldReceive('isApplicableToSubscriptionPlan')->andReturn(true);

        $this->repository->shouldReceive('findByCode')
            ->once()
            ->andReturn($voucher);

        $this->subscriptionPlanRepository->shouldReceive('find')->andReturn(null);

        $result = $this->service->validateVoucherForSubscription('TEST', 999);

        $this->assertFalse($result->valid);
        $this->assertEquals('Plan not found', $result->message);
    }

    public function testValidateVoucherForSubscriptionInvalidVoucher()
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->status = 'expired';
        $voucher->shouldReceive('isValid')->andReturn(false);

        $subscriptipnPlan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $subscriptipnPlan->price = 100;

        $this->subscriptionPlanRepository->shouldReceive('find')->andReturn($subscriptipnPlan);

        $this->validationService->shouldReceive('validate')
            ->once()
            ->andReturn(VoucherValidationResult::invalid('Voucher has expired'));

        $this->repository->shouldReceive('findByCode')
            ->once()
            ->andReturn($voucher);

        $result = $this->service->validateVoucherForSubscription('EXPIRED', 1);

        $this->assertFalse($result->valid);
        $this->assertEquals('Voucher has expired', $result->message);
    }

    public function testValidateVoucherForCheckoutWithMixedEligibility()
    {
        $code = 'MIXED10';

        $cartItems = [
            [
                'id' => 1,
                'product_id' => 1,
                'price' => 50.00,
                'quantity' => 1,
                'subtotal' => 50.00
            ],
            [
                'id' => 2,
                'product_id' => 2, // Not eligible
                'price' => 50.00,
                'quantity' => 1,
                'subtotal' => 50.00
            ],
            [
                'id' => 3,
                'subscription_plan_id' => 1,
                'price' => 30.00,
                'quantity' => 1,
                'subtotal' => 30.00
            ]
        ];

        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $voucher->id = 1;
        $voucher->minimum_order_value = null;
        $voucher->per_user_limit = null;
        $voucher->campaign_id = null;
        $voucher->is_stackable = true;

        $voucher->shouldReceive('isValid')->andReturn(true);
        $voucher->shouldReceive('isApplicableToProduct')->with(1)->andReturn(true);
        $voucher->shouldReceive('isApplicableToProduct')->with(2)->andReturn(false);
        $voucher->shouldReceive('isApplicableToSubscriptionPlan')->with(1)->andReturn(true);
        $voucher->shouldReceive('calculateDiscount')->with(80.00)->andReturn(8.00);

        $this->validationService->shouldReceive('validate')
            ->once()
            ->andReturn(VoucherValidationResult::valid($voucher, 8, 10, 80, ['test', 'test']));

        $this->repository->shouldReceive('findByCode')
            ->once()
            ->andReturn($voucher);

        $result = $this->service->validateVoucherForCheckout($code, $cartItems);

        $this->assertTrue($result->valid);
        $this->assertEquals(80.00, $result->eligibleSubtotal); // 50 + 30
        $this->assertCount(2, $result->eligibleItems);
        $this->assertEquals(8.00, $result->discount);
    }

    public function testValidateVoucherForCheckoutReturnsInvalidWhenNotFound(): void
    {
        $this->repository->expects('findByCode')->with('NONE')->andReturn(null);

        $result = $this->service->validateVoucherForCheckout('NONE', []);

        $this->assertFalse($result->valid);
        $this->assertSame('Voucher not found', $result->message);
    }

    public function testValidateVoucherForCheckoutDetectsOfferDiscountFromItemType(): void
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $validResult = Mockery::mock(VoucherValidationResult::class)->makePartial();

        $cartItems = [['item_type' => 'offer', 'price' => 10, 'base_price' => 10]];

        $this->repository->expects('findByCode')->andReturn($voucher);

        $this->validationService
            ->expects('validate')
            ->withArgs(function (Voucher $v, VoucherValidationContext $ctx) {
                return $ctx->hasOfferDiscount === true;
            })
            ->andReturn($validResult);

        $result = $this->service->validateVoucherForCheckout('CODE', $cartItems);

        $this->assertSame($validResult, $result);
    }

    public function testValidateVoucherForCheckoutDetectsOfferDiscountFromPriceDiff(): void
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $validResult = Mockery::mock(VoucherValidationResult::class)->makePartial();

        $cartItems = [['price' => 8.0, 'base_price' => 10.0]];

        $this->repository->expects('findByCode')->andReturn($voucher);

        $this->validationService
            ->expects('validate')
            ->withArgs(function (Voucher $v, VoucherValidationContext $ctx) {
                return $ctx->hasOfferDiscount === true;
            })
            ->andReturn($validResult);

        $result = $this->service->validateVoucherForCheckout('CODE', $cartItems);

        $this->assertSame($validResult, $result);
    }

    public function testValidateVoucherForCheckoutPassesFalseWhenNoOfferDiscount(): void
    {
        $voucher = Mockery::mock(Voucher::class)->makePartial();
        $validResult = Mockery::mock(VoucherValidationResult::class)->makePartial();

        $cartItems = [['price' => 10.0, 'base_price' => 10.0]];

        $this->repository->expects('findByCode')->andReturn($voucher);

        $this->validationService
            ->expects('validate')
            ->withArgs(function (Voucher $v, VoucherValidationContext $ctx) {
                return $ctx->hasOfferDiscount === false;
            })
            ->andReturn($validResult);

        $result = $this->service->validateVoucherForCheckout('CODE', $cartItems);

        $this->assertSame($validResult, $result);
    }

    public function testInvalidPlanReturnsInvalidResult()
    {
        $voucher = Mockery::mock(Voucher::class);

        $this->repository
            ->shouldReceive('findByCode')
            ->once()
            ->with('VALIDCODE')
            ->andReturn($voucher);

        $this->subscriptionPlanRepository
            ->shouldReceive('find')
            ->once()
            ->with(999, ['pricingTiers'])
            ->andReturn(null);

        $result = $this->service->validateVoucherForSubscription('VALIDCODE', 999);

        $this->assertFalse($result->valid);
        $this->assertEquals('Plan not found', $result->message);
    }

    public function testInvalidPricingTierReturnsInvalidResult()
    {
        $voucher = Mockery::mock(Voucher::class);
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $pricing = Mockery::mock(SubscriptionPlanPricing::class)->makePartial();
        $plan->price = 50;
        $plan->pricingTiers = collect([$pricing]);

        $this->repository
            ->shouldReceive('findByCode')->once()->andReturn($voucher);

        $this->subscriptionPlanRepository
            ->shouldReceive('find')->once()->andReturn($plan);

        $result = $this->service->validateVoucherForSubscription('VALIDCODE', 1, null, 999);

        $this->assertFalse($result->valid);
        $this->assertEquals('Invalid pricing tier', $result->message);
    }

    public function testValidVoucherCallsValidationServiceWithCorrectPrice()
    {
        $voucher = Mockery::mock(Voucher::class);
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->price = 100;

        $tier = Mockery::mock(SubscriptionPlanPricing::class)->makePartial();
        $tier->id = 10;
        $tier->price = 120;
        $tier->sale_price = 90;
        $tier->digital_price = 80;
        $tier->digital_sale_price = 70;

        $plan->pricingTiers = collect([$tier]);

        $this->repository
            ->shouldReceive('findByCode')
            ->once()
            ->with('VALIDCODE')
            ->andReturn($voucher);

        $this->subscriptionPlanRepository
            ->shouldReceive('find')
            ->once()
            ->with(1, ['pricingTiers'])
            ->andReturn($plan);

        $this->validationService
            ->shouldReceive('validate')
            ->once()
            ->with($voucher, Mockery::any())
            ->andReturn($result = Mockery::mock(VoucherValidationResult::class));

        $returned = $this->service->validateVoucherForSubscription('VALIDCODE', 1, null, 10);

        $this->assertSame($result, $returned);
    }

    public function testDigitalPricingTierPriceIsUsed()
    {
        $voucher = Mockery::mock(Voucher::class);
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->price = 100;

        $tier = new SubscriptionPlanPricing();
        $tier->id = 10;
        $tier->digital_price = 80;
        $tier->digital_sale_price = 70;

        $plan->pricingTiers = collect([$tier]);

        $this->repository->shouldReceive('findByCode')->andReturn($voucher);
        $this->subscriptionPlanRepository->shouldReceive('find')->andReturn($plan);

        $this->validationService
            ->shouldReceive('validate')
            ->once()
            ->with($voucher, Mockery::on(fn($context) => $context->orderValue == 70))
            ->andReturn(Mockery::mock(VoucherValidationResult::class));

        $this->service->validateVoucherForSubscription('CODE', 1, null, 10, SubscriptionType::DIGITAL->value);
        $this->assertTrue(true);
    }
}
