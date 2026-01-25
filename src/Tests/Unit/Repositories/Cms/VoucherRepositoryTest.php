<?php

namespace App\Tests\Unit\Repositories\Cms;

use App\Models\Voucher;
use App\Models\VoucherRedemption;
use App\Repositories\Cms\VoucherRepository;
use App\Search\SearchCriteria;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

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
    public function test_check_deletable_throws_exception_when_not_found(): void
    {
        // Expect
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Voucher not found');

        // Act
        $this->repository->checkDeletable(99999);
    }

    public function test_get_alternatives_returns_other_active_vouchers(): void
    {
        // Arrange
        $voucher = $this->createVoucher(['code' => 'MAIN', 'status' => 'active']);
        $alternative1 = $this->createVoucher(['code' => 'ALT1', 'status' => 'active']);
        $alternative2 = $this->createVoucher(['code' => 'ALT2', 'status' => 'active']);
        $inactiveVoucher = $this->createVoucher(['code' => 'INACTIVE', 'status' => 'inactive']);

        // Act
        $alternatives = $this->repository->getAlternatives($voucher->id);

        // Assert
        $alternativeIds = $alternatives->pluck('id')->toArray();

        $this->assertContains($alternative1->id, $alternativeIds);
        $this->assertContains($alternative2->id, $alternativeIds);
        $this->assertNotContains($voucher->id, $alternativeIds); // Should exclude itself
        $this->assertNotContains($inactiveVoucher->id, $alternativeIds);
    }

    public function test_get_alternatives_filters_by_site(): void
    {
        $site = $this->createSite();
        // Arrange
        $voucher = $this->createVoucher(['site_id' => $this->siteId]);
        $sameSiteVoucher = $this->createVoucher(['site_id' => $this->siteId, 'status' => 'active']);
        $otherSiteVoucher = $this->createVoucher(['site_id' => $site->id, 'status' => 'active']);

        // Act
        $alternatives = $this->repository->getAlternatives($voucher->id, $this->siteId);

        // Assert
        $alternativeIds = $alternatives->pluck('id')->toArray();

        $this->assertContains($sameSiteVoucher->id, $alternativeIds);
        $this->assertNotContains($otherSiteVoucher->id, $alternativeIds);
    }

    public function test_get_alternatives_returns_empty_collection_when_voucher_not_found(): void
    {
        // Act
        $alternatives = $this->repository->getAlternatives(99999);

        // Assert
        $this->assertInstanceOf(\App\Framework\Support\Collection::class, $alternatives);
        $this->assertCount(0, $alternatives);
    }

    public function test_code_exists_in_site_returns_true_when_exists(): void
    {
        // Arrange
        $this->createVoucher(['code' => 'EXISTINGCODE', 'site_id' => $this->siteId]);

        // Act
        $exists = $this->repository->codeExistsInSite('EXISTINGCODE', $this->siteId);

        // Assert
        $this->assertTrue($exists);
    }

    public function test_code_exists_in_site_returns_false_when_not_exists(): void
    {
        // Act
        $exists = $this->repository->codeExistsInSite('NONEXISTENT', $this->siteId);

        // Assert
        $this->assertFalse($exists);
    }

    public function test_code_exists_in_site_filters_by_site(): void
    {
        $site = $this->createSite();
        // Arrange
        $this->createVoucher(['code' => 'TESTCODE', 'site_id' => $site->id]);

        // Act
        $exists = $this->repository->codeExistsInSite('TESTCODE', $this->siteId);

        // Assert
        $this->assertFalse($exists); // Should not find code in different site
    }

    public function test_code_exists_in_site_excludes_specific_id(): void
    {
        // Arrange
        $voucher = $this->createVoucher(['code' => 'TESTCODE', 'site_id' => $this->siteId]);

        // Act
        $exists = $this->repository->codeExistsInSite('TESTCODE', $this->siteId, $voucher->id);

        // Assert
        $this->assertFalse($exists); // Should return false because we're excluding this voucher
    }

    public function test_code_exists_in_site_with_exclude_id_still_finds_other_matches(): void
    {
        // Arrange
        $voucher1 = $this->createVoucher(['code' => 'SAMECODE', 'site_id' => $this->siteId]);
        $voucher2 = $this->createVoucher(['code' => 'SAMECODE', 'site_id' => $this->siteId]);

        // Act
        $exists = $this->repository->codeExistsInSite('SAMECODE', $this->siteId, $voucher1->id);

        // Assert
        $this->assertTrue($exists); // Should still find voucher2
    }

    public function test_update_expired_vouchers_expires_vouchers_past_expiry_date(): void
    {
        // Arrange
        $expiredVoucher1 = $this->createVoucher([
            'status' => 'active',
            'expires_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
        ]);

        $expiredVoucher2 = $this->createVoucher([
            'status' => 'active',
            'expires_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))
        ]);

        $activeVoucher = $this->createVoucher([
            'status' => 'active',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 day'))
        ]);

        // Act
        $count = $this->repository->updateExpiredVouchers();

        // Assert
        $this->assertEquals(2, $count);

        $this->assertEquals('expired', $this->fresh($expiredVoucher1)->status);
        $this->assertEquals('expired', $this->fresh($expiredVoucher2)->status);
        $this->assertEquals('active', $this->fresh($activeVoucher)->status);
    }

    public function test_update_expired_vouchers_only_affects_current_site(): void
    {
        // Arrange
        $thisSiteVoucher = $this->createVoucher([
            'site_id' => $this->siteId,
            'status' => 'active',
            'expires_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
        ]);

        $otherSiteVoucher = $this->createVoucher();

        // Act
        $count = $this->repository->updateExpiredVouchers();

        // Assert
        $this->assertEquals(1, $count); // Only this site's voucher
        $this->assertEquals('expired', $this->fresh($thisSiteVoucher)->status);
        $this->assertEquals('active', $this->fresh($otherSiteVoucher)->status);
    }

    public function test_update_expired_vouchers_returns_zero_when_none_expired(): void
    {
        // Arrange
        $this->createVoucher([
            'status' => 'active',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 day'))
        ]);

        // Act
        $count = $this->repository->updateExpiredVouchers();

        // Assert
        $this->assertEquals(0, $count);
    }

    public function test_update_expired_vouchers_ignores_already_expired_status(): void
    {
        // Arrange
        $this->createVoucher([
            'status' => 'expired',
            'expires_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
        ]);

        // Act
        $count = $this->repository->updateExpiredVouchers();

        // Assert
        $this->assertEquals(0, $count); // Should not process already expired vouchers
    }

    public function test_update_expired_vouchers_ignores_inactive_vouchers(): void
    {
        // Arrange
        $this->createVoucher([
            'status' => 'inactive',
            'expires_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
        ]);

        // Act
        $count = $this->repository->updateExpiredVouchers();

        // Assert
        $this->assertEquals(0, $count);
    }

    public function test_search_returns_vouchers_with_category_and_brand_relationships(): void
    {
        $voucher = $this->createVoucher();
        $category = $this->createCategory();
        $brand = $this->createBrand();

        $voucher->categories(true)->attach($category->id);
        $voucher->brands(true)->attach($brand->id);

        $criteria = new SearchCriteria();
        $result = $this->repository->search($criteria);

        $this->assertGreaterThan(0, count($result->getData()));
        $foundVoucher = $result->getData()[0];

        $this->assertArrayHasKey('categories', $foundVoucher);
        $this->assertArrayHasKey('brands', $foundVoucher);
    }

    public function test_sync_categories_attaches_categories_to_voucher(): void
    {
        $voucher = $this->createVoucher();
        $category1 = $this->createCategory();
        $category2 = $this->createCategory();

        $this->repository->syncCategories($voucher->id, [$category1->id, $category2->id]);

        $voucher = Voucher::find($voucher->id);
        $this->assertCount(2, $voucher->categories);
    }

    public function test_sync_categories_replaces_existing_categories(): void
    {
        $voucher = $this->createVoucher();
        $category1 = $this->createCategory();
        $category2 = $this->createCategory();
        $category3 = $this->createCategory();

        $voucher->categories(true)->attach([$category1->id, $category2->id]);

        $this->repository->syncCategories($voucher->id, [$category3->id]);

        $voucher = Voucher::find($voucher->id);
        $categoryIds = $voucher->categories()->pluck('id')->toArray();

        $this->assertCount(1, $categoryIds);
        $this->assertContains($category3->id, $categoryIds);
        $this->assertNotContains($category1->id, $categoryIds);
        $this->assertNotContains($category2->id, $categoryIds);
    }

    public function test_sync_brands_attaches_brands_to_voucher(): void
    {
        $voucher = $this->createVoucher();
        $brand1 = $this->createBrand();
        $brand2 = $this->createBrand();
        $this->repository->syncBrands($voucher->id, [$brand1->id, $brand2->id]);

        $voucher = Voucher::find($voucher->id);
        $this->assertCount(2, $voucher->brands);
    }

    public function test_sync_brands_replaces_existing_brands(): void
    {
        $voucher = $this->createVoucher();
        $brand1 = $this->createBrand();
        $brand2 = $this->createBrand();
        $brand3 = $this->createBrand();
        $voucher->brands(true)->attach([$brand1->id, $brand2->id]);

        $this->repository->syncBrands($voucher->id, [$brand3->id]);

        $voucher = Voucher::find($voucher->id);
        $brandIds = $voucher->brands()->pluck('id')->toArray();

        $this->assertCount(1, $brandIds);
        $this->assertContains($brand3->id, $brandIds);
        $this->assertNotContains($brand1->id, $brandIds);
        $this->assertNotContains($brand2->id, $brandIds);
    }

    public function test_sync_categories_handles_empty_array(): void
    {
        $voucher = $this->createVoucher();
        $category1 = $this->createCategory();
        $voucher->categories(true)->attach($category1->id);

        $this->repository->syncCategories($voucher->id, []);

        $voucher = Voucher::find($voucher->id);
        $this->assertCount(0, $voucher->categories);
    }

    public function test_sync_brands_handles_empty_array(): void
    {
        $voucher = $this->createVoucher();
        $brand1 = $this->createBrand();
        $voucher->brands(true)->attach($brand1->id);

        $this->repository->syncBrands($voucher->id, []);

        $voucher = Voucher::find($voucher->id);
        $this->assertCount(0, $voucher->brands);
    }

    public function test_sync_products_attaches_products_to_voucher(): void
    {
        // Arrange
        $voucher = $this->createVoucher();
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();

        // Act
        $this->repository->syncProducts($voucher->id, [$product1->id, $product2->id]);

        // Assert
        $voucher = Voucher::find($voucher->id);
        $this->assertCount(2, $voucher->products);
    }

    public function test_sync_products_replaces_existing_products(): void
    {
        // Arrange
        $voucher = $this->createVoucher();
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();
        $product3 = $this->createProduct();

        $voucher->products(true)->attach([$product1->id, $product2->id]);

        // Act
        $this->repository->syncProducts($voucher->id, [$product3->id]);

        // Assert
        $voucher = Voucher::find($voucher->id);
        $productIds = $voucher->products()->pluck('id')->toArray();

        $this->assertCount(1, $productIds);
        $this->assertContains($product3->id, $productIds);
        $this->assertNotContains($product1->id, $productIds);
        $this->assertNotContains($product2->id, $productIds);
    }

    public function test_sync_products_handles_empty_array(): void
    {
        // Arrange
        $voucher = $this->createVoucher();
        $product1 = $this->createProduct();

        $voucher->products(true)->attach($product1->id);

        // Act
        $this->repository->syncProducts($voucher->id, []);

        // Assert
        $voucher = Voucher::find($voucher->id);
        $this->assertCount(0, $voucher->products);
    }

    public function test_sync_products_does_nothing_when_voucher_not_found(): void
    {
        // Arrange
        $product1 = $this->createProduct();

        // Act - should not throw exception
        $this->repository->syncProducts(99999, [$product1->id]);

        // Assert - no exception thrown, method completes silently
        $this->assertTrue(true);
    }

    public function test_sync_products_maintains_correct_associations(): void
    {
        // Arrange
        $voucher = $this->createVoucher();
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();
        $product3 = $this->createProduct();

        // Initially attach products 1 and 2
        $voucher->products(true)->attach([$product1->id, $product2->id]);

        // Act - sync to products 2 and 3 (remove 1, keep 2, add 3)
        $this->repository->syncProducts($voucher->id, [$product2->id, $product3->id]);

        // Assert
        $voucher = Voucher::find($voucher->id);
        $productIds = $voucher->products()->pluck('id')->toArray();

        $this->assertCount(2, $productIds);
        $this->assertNotContains($product1->id, $productIds);
        $this->assertContains($product2->id, $productIds);
        $this->assertContains($product3->id, $productIds);
    }

    public function test_sync_products_handles_duplicate_ids_in_array(): void
    {
        // Arrange
        $voucher = $this->createVoucher();
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();

        // Act - sync with duplicate IDs
        $this->repository->syncProducts($voucher->id, [$product1->id, $product2->id, $product1->id]);

        // Assert - should only attach each product once
        $voucher = Voucher::find($voucher->id);
        $productIds = $voucher->products()->pluck('id')->toArray();

        $this->assertCount(2, $productIds);
        $this->assertContains($product1->id, $productIds);
        $this->assertContains($product2->id, $productIds);
    }

    public function test_sync_products_preserves_other_voucher_product_relationships(): void
    {
        // Arrange
        $voucher1 = $this->createVoucher();
        $voucher2 = $this->createVoucher();
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();

        $voucher1->products(true)->attach([$product1->id]);
        $voucher2->products(true)->attach([$product1->id, $product2->id]);

        // Act - sync voucher1 with product2
        $this->repository->syncProducts($voucher1->id, [$product2->id]);

        // Assert - voucher2 should still have both products
        $voucher1Fresh = Voucher::find($voucher1->id);
        $voucher2Fresh = Voucher::find($voucher2->id);

        $this->assertCount(1, $voucher1Fresh->products);
        $this->assertCount(2, $voucher2Fresh->products);
    }

    public function test_sync_products_can_handle_large_number_of_products(): void
    {
        // Arrange
        $voucher = $this->createVoucher();
        $productIds = [];

        for ($i = 0; $i < 50; $i++) {
            $product = $this->createProduct();
            $productIds[] = $product->id;
        }

        // Act
        $this->repository->syncProducts($voucher->id, $productIds);

        // Assert
        $voucher = Voucher::find($voucher->id);
        $this->assertCount(50, $voucher->products);
    }

    public function test_sync_products_removes_all_products_when_syncing_empty_array(): void
    {
        // Arrange
        $voucher = $this->createVoucher();
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();
        $product3 = $this->createProduct();

        $voucher->products(true)->attach([$product1->id, $product2->id, $product3->id]);

        // Verify initial state
        $this->assertCount(3, $voucher->products);

        // Act
        $this->repository->syncProducts($voucher->id, []);

        // Assert
        $voucher = Voucher::find($voucher->id);
        $this->assertCount(0, $voucher->products);
    }

    public function test_sync_products_works_with_single_product(): void
    {
        // Arrange
        $voucher = $this->createVoucher();
        $product = $this->createProduct();

        // Act
        $this->repository->syncProducts($voucher->id, [$product->id]);

        // Assert
        $voucher = Voucher::find($voucher->id);
        $this->assertCount(1, $voucher->products);
        $this->assertEquals($product->id, $voucher->products->first()->id);
    }

    public function test_create_redemption_creates_record(): void
    {
        // Arrange
        $voucher = $this->createVoucher();
        $userId = $this->createMember()->id;
        $discountAmount = 15.50;

        // Act
        $result = $this->repository->createRedemption($voucher->id, $userId, $discountAmount);

        // Assert
        $this->assertTrue($result);

        $redemption = VoucherRedemption::where('voucher_id', $voucher->id)
            ->where('member_id', $userId)
            ->first();

        $this->assertNotNull($redemption);
        $this->assertEquals($discountAmount, $redemption->discount_amount);
        $this->assertNull($redemption->order_id);
    }

    public function test_create_redemption_with_order_id(): void
    {
        // Arrange
        $voucher = $this->createVoucher();
        $userId = $this->createMember()->id;
        $orderId = $this->createOrder()->id;
        $discountAmount = 20.00;

        // Act
        $result = $this->repository->createRedemption($voucher->id, $userId, $discountAmount, $orderId);

        // Assert
        $this->assertTrue($result);

        $redemption = VoucherRedemption::where('voucher_id', $voucher->id)
            ->where('member_id', $userId)
            ->first();

        $this->assertNotNull($redemption);
        $this->assertEquals($orderId, $redemption->order_id);
    }

    public function test_create_redemption_allows_null_user_id(): void
    {
        // Arrange
        $voucher = $this->createVoucher();
        $discountAmount = 10.00;

        // Act
        $result = $this->repository->createRedemption($voucher->id, null, $discountAmount);

        // Assert
        $this->assertTrue($result);

        $redemption = VoucherRedemption::where('voucher_id', $voucher->id)
            ->whereNull('member_id')
            ->first();

        $this->assertNotNull($redemption);
    }

    public function test_create_redemption_returns_false_on_failure(): void
    {
        // Act - invalid voucher ID
        $result = $this->repository->createRedemption(99999, 1, 10.00);

        // Assert
        $this->assertFalse($result);
    }

    public function test_get_redemptions_by_voucher_returns_all_redemptions(): void
    {
        // Arrange
        $voucher = $this->createVoucher();
        $user1 = $this->createMember();
        $user2 = $this->createMember();

        $this->repository->createRedemption($voucher->id, $user1->id, 10.00);
        $this->repository->createRedemption($voucher->id, $user2->id, 15.00);
        $this->repository->createRedemption($voucher->id, null, 20.00);

        // Act
        $redemptions = $this->repository->getRedemptionsByVoucher($voucher->id);

        // Assert
        $this->assertCount(3, $redemptions);
    }

    public function test_get_redemptions_by_voucher_orders_by_date_desc(): void
    {
        // Arrange
        $voucher = $this->createVoucher();
        $user = $this->createMember();

        // Create redemptions with different timestamps
        VoucherRedemption::create([
            'voucher_id' => $voucher->id,
            'member_id' => $user->id,
            'discount_amount' => 10.00,
            'redeemed_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
        ]);

        VoucherRedemption::create([
            'voucher_id' => $voucher->id,
            'member_id' => $user->id,
            'discount_amount' => 15.00,
            'redeemed_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
        ]);

        // Act
        $redemptions = $this->repository->getRedemptionsByVoucher($voucher->id);

        // Assert
        $this->assertEquals(15.00, $redemptions->first()->discount_amount);
    }

    public function test_get_redemptions_by_voucher_returns_empty_collection_when_none(): void
    {
        // Arrange
        $voucher = $this->createVoucher();

        // Act
        $redemptions = $this->repository->getRedemptionsByVoucher($voucher->id);

        // Assert
        $this->assertCount(0, $redemptions);
        $this->assertInstanceOf(\App\Framework\Support\Collection::class, $redemptions);
    }

    public function test_get_redemptions_by_user_returns_all_user_redemptions(): void
    {
        // Arrange
        $user = $this->createMember();
        $voucher1 = $this->createVoucher();
        $voucher2 = $this->createVoucher();
        $voucher3 = $this->createVoucher();

        $this->repository->createRedemption($voucher1->id, $user->id, 10.00);
        $this->repository->createRedemption($voucher2->id, $user->id, 15.00);
        $this->repository->createRedemption($voucher3->id, $user->id, 20.00);

        // Act
        $redemptions = $this->repository->getRedemptionsByUser($user->id);

        // Assert
        $this->assertCount(3, $redemptions);
    }

    public function test_get_redemptions_by_user_only_returns_users_redemptions(): void
    {
        // Arrange
        $user1 = $this->createMember();
        $user2 = $this->createMember();
        $voucher = $this->createVoucher();

        $this->repository->createRedemption($voucher->id, $user1->id, 10.00);
        $this->repository->createRedemption($voucher->id, $user2->id, 15.00);

        // Act
        $redemptions = $this->repository->getRedemptionsByUser($user1->id);

        // Assert
        $this->assertCount(1, $redemptions);

        $this->assertEquals($user1->id, $redemptions->first()->member_id);
    }

    public function test_get_redemptions_by_user_orders_by_date_desc(): void
    {
        // Arrange
        $user = $this->createMember();
        $voucher1 = $this->createVoucher();
        $voucher2 = $this->createVoucher();

        VoucherRedemption::create([
            'voucher_id' => $voucher1->id,
            'member_id' => $user->id,
            'discount_amount' => 10.00,
            'redeemed_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
        ]);

        VoucherRedemption::create([
            'voucher_id' => $voucher2->id,
            'member_id' => $user->id,
            'discount_amount' => 15.00,
            'redeemed_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
        ]);

        // Act
        $redemptions = $this->repository->getRedemptionsByUser($user->id);

        // Assert
        $this->assertEquals(15.00, $redemptions->first()->discount_amount);
    }

    public function test_get_redemptions_by_user_returns_empty_collection_when_none(): void
    {
        // Arrange
        $user = $this->createUser();

        // Act
        $redemptions = $this->repository->getRedemptionsByUser($user->id);

        // Assert
        $this->assertCount(0, $redemptions);
        $this->assertInstanceOf(\App\Framework\Support\Collection::class, $redemptions);
    }

}