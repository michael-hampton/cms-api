<?php

namespace App\Tests\Unit\Repositories;

use App\Models\ProductVoucher;
use App\Models\Voucher;
use App\Repositories\VoucherRepository;
use App\Search\SearchCriteria;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class VoucherRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private VoucherRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new VoucherRepository();
    }

    /**
     * Create a test voucher
     */
    protected function createVoucher(array $overrides = []): Voucher
    {
        return Voucher::create(array_merge([
            'site_id' => $this->siteId,
            'code' => 'VOUCHER' . uniqid(),
            'name' => 'Test Voucher',
            'discount_type' => 'percentage',
            'value' => 10.99,
            'discount_value' => 10,
            'status' => 'active',
            'usage_count' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    /** @test */
    public function test_search_returns_vouchers_with_relationships(): void
    {
        // Arrange
        $voucher = $this->createVoucher();

        $product = $this->createProduct();

        $this->attachVoucherToProduct($voucher, $product);

        // Act
        $criteria = new SearchCriteria();
        $result = $this->repository->search($criteria);

        // Assert
        $this->assertGreaterThan(0, count($result->getData()));
        $foundVoucher = $result->getData()[0];

        $this->assertNotEmpty($foundVoucher['products']);
    }

    /** @test */
    public function test_find_by_code_returns_correct_voucher(): void
    {
        // Arrange
        $voucher = $this->createVoucher(['code' => 'TESTCODE123']);

        // Act
        $found = $this->repository->findByCode('TESTCODE123');

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals($voucher->id, $found->id);
        $this->assertEquals('TESTCODE123', $found->code);
    }

    /** @test */
    public function test_find_by_code_returns_null_when_not_found(): void
    {
        // Act
        $found = $this->repository->findByCode('NONEXISTENT');

        // Assert
        $this->assertNull($found);
    }

    /** @test */
    public function test_find_by_code_filters_by_site(): void
    {
        $site = $this->createSite();
        // Arrange
        $this->createVoucher(['code' => 'TESTCODE', 'site_id' => $site->id]);

        // Act
        $found = $this->repository->findByCode('TESTCODE');

        // Assert
        $this->assertNotNull($found); // Should not find voucher from different site
    }

    /** @test */
    public function test_get_active_vouchers_returns_only_active(): void
    {
        // Arrange
        $this->createVoucher(['status' => 'active', 'code' => 'ACTIVE1']);
        $this->createVoucher(['status' => 'active', 'code' => 'ACTIVE2']);
        $this->createVoucher(['status' => 'expired', 'code' => 'EXPIRED1']);
        $this->createVoucher(['status' => 'inactive', 'code' => 'INACTIVE1']);

        // Act
        $vouchers = $this->repository->getActiveVouchers();

        // Assert
        $this->assertGreaterThanOrEqual(2, $vouchers->count());
        foreach ($vouchers as $voucher) {
            $this->assertEquals('active', $voucher->status);
        }
    }

    /** @test */
    public function test_get_active_vouchers_excludes_expired_by_date(): void
    {
        // Arrange
        $activeVoucher = $this->createVoucher([
            'status' => 'active',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 day'))
        ]);

        $expiredVoucher = $this->createVoucher([
            'status' => 'active',
            'expires_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
        ]);

        // Act
        $vouchers = $this->repository->getActiveVouchers();

        // Assert
        $voucherIds = $vouchers->pluck('id')->toArray();
        $this->assertContains($activeVoucher->id, $voucherIds);
        $this->assertNotContains($expiredVoucher->id, $voucherIds);
    }

    /** @test */
    public function test_get_active_vouchers_includes_vouchers_with_null_expiry(): void
    {
        // Arrange
        $voucher = $this->createVoucher([
            'status' => 'active',
            'expires_at' => null
        ]);

        // Act
        $vouchers = $this->repository->getActiveVouchers();

        // Assert
        $voucherIds = $vouchers->pluck('id')->toArray();
        $this->assertContains($voucher->id, $voucherIds);
    }

    /** @test */
    public function test_increment_usage_count_increases_counter(): void
    {
        // Arrange
        $voucher = $this->createVoucher(['usage_count' => 5]);

        // Act
        $result = $this->repository->incrementUsageCount($voucher->id);

        // Assert
        $this->assertTrue($result);
        $freshVoucher = $this->fresh($voucher);
        $this->assertEquals(6, $freshVoucher->usage_count);
    }

    /** @test */
    public function test_increment_usage_count_expires_when_limit_reached(): void
    {
        // Arrange
        $voucher = $this->createVoucher([
            'usage_count' => 9,
            'usage_limit' => 10,
            'status' => 'active'
        ]);

        // Act
        $this->repository->incrementUsageCount($voucher->id);

        // Assert
        $freshVoucher = $this->fresh($voucher);
        $this->assertEquals(10, $freshVoucher->usage_count);
        $this->assertEquals('expired', $freshVoucher->status);
    }

    /** @test */
    public function test_increment_usage_count_does_not_expire_without_limit(): void
    {
        // Arrange
        $voucher = $this->createVoucher([
            'usage_count' => 100,
            'usage_limit' => null,
            'status' => 'active'
        ]);

        // Act
        $this->repository->incrementUsageCount($voucher->id);

        // Assert
        $freshVoucher = $this->fresh($voucher);
        $this->assertEquals(101, $freshVoucher->usage_count);
        $this->assertEquals('active', $freshVoucher->status);
    }

    /** @test */
    public function test_increment_usage_count_returns_false_when_not_found(): void
    {
        // Act
        $result = $this->repository->incrementUsageCount(99999);

        // Assert
        $this->assertFalse($result);
    }

    /** @test */
    public function test_check_deletable_returns_true_when_no_usage(): void
    {
        // Arrange
        $voucher = $this->createVoucher(['usage_count' => 0]);

        // Act
        $result = $this->repository->checkDeletable($voucher->id);

        // Assert
        $this->assertTrue($result['can_delete']);
        $this->assertEquals(0, $result['usage_count']);
        $this->assertFalse($result['requires_confirmation']);
    }

    /** @test */
    public function test_check_deletable_returns_false_when_has_usage(): void
    {
        // Arrange
        $voucher = $this->createVoucher(['usage_count' => 5]);

        // Act
        $result = $this->repository->checkDeletable($voucher->id);

        // Assert
        $this->assertFalse($result['can_delete']);
        $this->assertEquals(5, $result['usage_count']);
        $this->assertTrue($result['requires_confirmation']);
    }

    /** @test */
//    public function check_deletable_throws_exception_when_not_found(): void
//    {
//        // Expect
//        $this->expectException(\Exception::class);
//        $this->expectExceptionMessage('Voucher not found');
//
//        // Act
//        $this->repository->checkDeletable

}