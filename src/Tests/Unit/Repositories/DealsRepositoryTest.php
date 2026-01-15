<?php

namespace App\Tests\Unit\Repositories;

use App\Models\FeaturedDeal;
use App\Repositories\Product\DealsRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class DealsRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private DealsRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new DealsRepository();
    }

    public function test_get_featured_deals_by_date_returns_active_deals(): void
    {
        $today = date('Y-m-d');

        $deal1 = FeaturedDeal::create([
            'site_id' => $this->siteId,
            'product_id' => $this->createProduct()->id,
            'featured_date' => $today,
            'is_active' => true,
            'position' => 1
        ]);

        $deal2 = FeaturedDeal::create([
            'site_id' => $this->siteId,
            'product_id' => $this->createProduct()->id,
            'featured_date' => $today,
            'is_active' => true,
            'position' => 2
        ]);

        $inactiveDeal = FeaturedDeal::create([
            'site_id' => $this->siteId,
            'product_id' => $this->createProduct()->id,
            'featured_date' => $today,
            'is_active' => false,
            'position' => 3
        ]);

        $deals = $this->repository->getFeaturedDealsByDate($this->siteId, $today, 10);

        $this->assertCount(2, $deals);
        $this->assertEquals(1, $deals[0]['position']);
        $this->assertEquals(2, $deals[1]['position']);
    }

    public function test_get_featured_deals_by_date_filters_by_site(): void
    {
        $today = date('Y-m-d');
        $otherSite = $this->createSite();

        FeaturedDeal::create([
            'site_id' => $this->siteId,
            'product_id' => $this->createProduct()->id,
            'featured_date' => $today,
            'is_active' => true,
            'position' => 1
        ]);

        FeaturedDeal::create([
            'site_id' => $otherSite->id,
            'product_id' => $this->createProduct(['site_id' => $otherSite->id])->id,
            'featured_date' => $today,
            'is_active' => true,
            'position' => 1
        ]);

        $deals = $this->repository->getFeaturedDealsByDate($this->siteId, $today, 10);

        $this->assertCount(1, $deals);
        $this->assertEquals($this->siteId, $deals[0]['site_id']);
    }

    public function test_get_featured_deals_by_date_respects_limit(): void
    {
        $today = date('Y-m-d');

        for ($i = 1; $i <= 10; $i++) {
            FeaturedDeal::create([
                'site_id' => $this->siteId,
                'product_id' => $this->createProduct()->id,
                'featured_date' => $today,
                'is_active' => true,
                'position' => $i
            ]);
        }

        $deals = $this->repository->getFeaturedDealsByDate($this->siteId, $today, 5);

        $this->assertCount(5, $deals);
    }

    public function test_get_featured_deals_by_date_orders_by_position(): void
    {
        $today = date('Y-m-d');

        FeaturedDeal::create([
            'site_id' => $this->siteId,
            'product_id' => $this->createProduct()->id,
            'featured_date' => $today,
            'is_active' => true,
            'position' => 3
        ]);

        FeaturedDeal::create([
            'site_id' => $this->siteId,
            'product_id' => $this->createProduct()->id,
            'featured_date' => $today,
            'is_active' => true,
            'position' => 1
        ]);

        FeaturedDeal::create([
            'site_id' => $this->siteId,
            'product_id' => $this->createProduct()->id,
            'featured_date' => $today,
            'is_active' => true,
            'position' => 2
        ]);

        $deals = $this->repository->getFeaturedDealsByDate($this->siteId, $today, 10);

        $this->assertEquals(1, $deals[0]['position']);
        $this->assertEquals(2, $deals[1]['position']);
        $this->assertEquals(3, $deals[2]['position']);
    }

    public function test_deactivate_old_featured_deals_updates_correct_deals(): void
    {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $twoDaysAgo = date('Y-m-d', strtotime('-2 days'));

        $oldDeal1 = FeaturedDeal::create([
            'site_id' => $this->siteId,
            'product_id' => $this->createProduct()->id,
            'featured_date' => $yesterday,
            'is_active' => true,
            'position' => 1
        ]);

        $oldDeal2 = FeaturedDeal::create([
            'site_id' => $this->siteId,
            'product_id' => $this->createProduct()->id,
            'featured_date' => $twoDaysAgo,
            'is_active' => true,
            'position' => 1
        ]);

        $currentDeal = FeaturedDeal::create([
            'site_id' => $this->siteId,
            'product_id' => $this->createProduct()->id,
            'featured_date' => $today,
            'is_active' => true,
            'position' => 1
        ]);

        $count = $this->repository->deactivateOldFeaturedDeals($this->siteId, $today);

        $this->assertEquals(2, $count);

        $this->assertFalse(FeaturedDeal::find($oldDeal1->id)->is_active);
        $this->assertFalse(FeaturedDeal::find($oldDeal2->id)->is_active);
        $this->assertTrue(FeaturedDeal::find($currentDeal->id)->is_active);
    }

    public function test_deactivate_old_featured_deals_filters_by_site(): void
    {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $otherSite = $this->createSite();

        $thisSiteDeal = FeaturedDeal::create([
            'site_id' => $this->siteId,
            'product_id' => $this->createProduct()->id,
            'featured_date' => $yesterday,
            'is_active' => true,
            'position' => 1
        ]);

        $otherSiteDeal = FeaturedDeal::create([
            'site_id' => $otherSite->id,
            'product_id' => $this->createProduct(['site_id' => $otherSite->id])->id,
            'featured_date' => $yesterday,
            'is_active' => true,
            'position' => 1
        ]);

        $count = $this->repository->deactivateOldFeaturedDeals($this->siteId, $today);

        $this->assertEquals(1, $count);
        $this->assertFalse(FeaturedDeal::find($thisSiteDeal->id)->is_active);
        $this->assertTrue(FeaturedDeal::find($otherSiteDeal->id)->is_active);
    }

    public function test_deactivate_featured_deals_by_date_updates_specific_date(): void
    {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $todayDeal = FeaturedDeal::create([
            'site_id' => $this->siteId,
            'product_id' => $this->createProduct()->id,
            'featured_date' => $today,
            'is_active' => true,
            'position' => 1
        ]);

        $yesterdayDeal = FeaturedDeal::create([
            'site_id' => $this->siteId,
            'product_id' => $this->createProduct()->id,
            'featured_date' => $yesterday,
            'is_active' => true,
            'position' => 1
        ]);

        $count = $this->repository->deactivateFeaturedDealsByDate($this->siteId, $today);

        $this->assertEquals(1, $count);
        $this->assertFalse(FeaturedDeal::find($todayDeal->id)->is_active);
        $this->assertTrue(FeaturedDeal::find($yesterdayDeal->id)->is_active);
    }

    public function test_create_featured_deal_creates_new_deal(): void
    {
        $product = $this->createProduct();
        $today = date('Y-m-d');

        $deal = $this->repository->createFeaturedDeal([
            'site_id' => $this->siteId,
            'product_id' => $product->id,
            'variant_id' => null,
            'merchant_id' => null,
            'featured_date' => $today,
            'position' => 1,
            'is_active' => true
        ]);

        $this->assertNotNull($deal);
        $this->assertEquals($this->siteId, $deal->site_id);
        $this->assertEquals($product->id, $deal->product_id);
        $this->assertEquals($today, $deal->featured_date->format('Y-m-d'));
        $this->assertTrue($deal->is_active);
    }

    public function test_get_products_for_deals_returns_active_products(): void
    {
        $activeProduct = $this->createProduct([
            'is_active' => true,
            'price' => 100.00,
            'sale_price' => 80.00
        ]);

        $inactiveProduct = $this->createProduct([
            'is_active' => false,
            'price' => 100.00,
            'sale_price' => 80.00
        ]);

        $products = $this->repository->getProductsForDeals($this->siteId);

        $productIds = array_column($products, 'id');
        $this->assertContains($activeProduct->id, $productIds);
        $this->assertNotContains($inactiveProduct->id, $productIds);
    }

    public function test_get_products_for_deals_requires_sale_price(): void
    {
        $withSalePrice = $this->createProduct([
            'is_active' => true,
            'price' => 100.00,
            'sale_price' => 80.00
        ]);

        $withoutSalePrice = $this->createProduct([
            'is_active' => true,
            'price' => 100.00,
            'sale_price' => 0
        ]);

        $products = $this->repository->getProductsForDeals($this->siteId);

        $productIds = array_column($products, 'id');

        $this->assertContains($withSalePrice->id, $productIds);
        $this->assertNotContains($withoutSalePrice->id, $productIds);
    }

    public function test_get_products_for_deals_applies_price_range(): void
    {
        $inRange = $this->createProduct([
            'is_active' => true,
            'price' => 100.00,
            'sale_price' => 50.00
        ]);

        $tooLow = $this->createProduct([
            'is_active' => true,
            'price' => 100.00,
            'sale_price' => 5.00
        ]);

        $tooHigh = $this->createProduct([
            'is_active' => true,
            'price' => 500.00,
            'sale_price' => 400.00
        ]);

        $products = $this->repository->getProductsForDeals($this->siteId, 10, 300);

        $productIds = array_column($products, 'id');
        $this->assertContains($inRange->id, $productIds);
        $this->assertNotContains($tooLow->id, $productIds);
        $this->assertNotContains($tooHigh->id, $productIds);
    }

    public function test_get_products_for_deals_filters_by_site(): void
    {
        $otherSite = $this->createSite();

        $thisSiteProduct = $this->createProduct([
            'site_id' => $this->siteId,
            'is_active' => true,
            'sale_price' => 50.00
        ]);

        $otherSiteProduct = $this->createProduct([
            'site_id' => $otherSite->id,
            'is_active' => true,
            'sale_price' => 50.00
        ]);

        $products = $this->repository->getProductsForDeals($this->siteId);

        $productIds = array_column($products, 'id');
        $this->assertContains($thisSiteProduct->id, $productIds);
        $this->assertNotContains($otherSiteProduct->id, $productIds);
    }

    public function test_get_filtered_products_applies_category_filter(): void
    {
        $category1 = $this->createCategory();
        $category2 = $this->createCategory();

        $product1 = $this->createProduct(['category_id' => $category1->id, 'is_active' => true]);
        $product2 = $this->createProduct(['category_id' => $category2->id, 'is_active' => true]);
        $product3 = $this->createProduct(['category_id' => null, 'is_active' => true]);

        $products = $this->repository->getFilteredProducts($this->siteId, [
            'category_ids' => [$category1->id]
        ]);

        $productIds = $products['data']->pluck('id')->toArray();

        $this->assertContains($product1->id, $productIds);
        $this->assertNotContains($product2->id, $productIds);
        $this->assertNotContains($product3->id, $productIds);
    }

    public function test_get_filtered_products_applies_brand_filter(): void
    {
        $brand1 = $this->createBrand();
        $brand2 = $this->createBrand();

        $product1 = $this->createProduct(['brand_id' => $brand1->id, 'is_active' => true]);
        $product2 = $this->createProduct(['brand_id' => $brand2->id, 'is_active' => true]);

        $products = $this->repository->getFilteredProducts($this->siteId, [
            'brand_ids' => [$brand1->id]
        ]);

        $productIds = $products['data']->pluck('id')->toArray();
        $this->assertContains($product1->id, $productIds);
        $this->assertNotContains($product2->id, $productIds);
    }

    public function test_get_filtered_products_applies_price_range(): void
    {
        $product1 = $this->createProduct(['sale_price' => 25.00, 'is_active' => true]);
        $product2 = $this->createProduct(['sale_price' => 75.00, 'is_active' => true]);
        $product3 = $this->createProduct(['sale_price' => 150.00, 'is_active' => true]);

        $products = $this->repository->getFilteredProducts($this->siteId, [
            'min_price' => 50,
            'max_price' => 100
        ]);

        $productIds = $products['data']->pluck('id')->toArray();
        $this->assertNotContains($product1->id, $productIds);
        $this->assertContains($product2->id, $productIds);
        $this->assertNotContains($product3->id, $productIds);
    }

    public function test_get_filtered_products_applies_min_price_only(): void
    {
        $product1 = $this->createProduct(['sale_price' => 25.00, 'is_active' => true]);
        $product2 = $this->createProduct(['sale_price' => 75.00, 'is_active' => true]);

        $products = $this->repository->getFilteredProducts($this->siteId, [
            'min_price' => 50
        ]);

        $productIds = $products['data']->pluck('id')->toArray();
        $this->assertNotContains($product1->id, $productIds);
        $this->assertContains($product2->id, $productIds);
    }

    public function test_get_filtered_products_applies_max_price_only(): void
    {
        $product1 = $this->createProduct(['sale_price' => 25.00, 'is_active' => true]);
        $product2 = $this->createProduct(['sale_price' => 75.00, 'is_active' => true]);

        $products = $this->repository->getFilteredProducts($this->siteId, [
            'max_price' => 50
        ]);

        $productIds = $products['data']->pluck('id')->toArray();
        $this->assertContains($product1->id, $productIds);
        $this->assertNotContains($product2->id, $productIds);
    }

//    public function test_get_filtered_products_applies_voucher_filter(): void
//    {
//        // This test assumes there's an activeVouchers relationship
//        $product1 = $this->createProduct(['is_active' => true]);
//        $product2 = $this->createProduct(['is_active' => true]);
//
//        // Note: You may need to create vouchers for this test to work properly
//        $products = $this->repository->getFilteredProducts($this->siteId, [
//            'hasVoucher' => true
//        ]);
//
//        $this->assertIsArray($products);
//    }

    public function test_get_filtered_products_filters_by_site(): void
    {
        $otherSite = $this->createSite();

        $thisSiteProduct = $this->createProduct(['site_id' => $this->siteId, 'is_active' => true]);
        $otherSiteProduct = $this->createProduct(['site_id' => $otherSite->id, 'is_active' => true]);

        $products = $this->repository->getFilteredProducts($this->siteId, []);

        $productIds = $products['data']->pluck('id')->toArray();
        $this->assertContains($thisSiteProduct->id, $productIds);
        $this->assertNotContains($otherSiteProduct->id, $productIds);
    }

    public function test_get_filtered_products_returns_only_active_products(): void
    {
        $activeProduct = $this->createProduct(['is_active' => true]);
        $inactiveProduct = $this->createProduct(['is_active' => false]);

        $products = $this->repository->getFilteredProducts($this->siteId, []);

        $productIds = $products['data']->pluck('id')->toArray();
        $this->assertContains($activeProduct->id, $productIds);
        $this->assertNotContains($inactiveProduct->id, $productIds);
    }

    public function test_find_product_by_id_returns_null_when_not_found(): void
    {
        $found = $this->repository->findProductById(999999);

        $this->assertNull($found);
    }

    public function test_get_filtered_products_combines_multiple_filters(): void
    {
        $category = $this->createCategory();
        $brand = $this->createBrand();

        $matchingProduct = $this->createProduct([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sale_price' => 50.00,
            'is_active' => true
        ]);

        $wrongCategory = $this->createProduct([
            'category_id' => $this->createCategory()->id,
            'brand_id' => $brand->id,
            'sale_price' => 50.00,
            'is_active' => true
        ]);

        $wrongPrice = $this->createProduct([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sale_price' => 150.00,
            'is_active' => true
        ]);

        $products = $this->repository->getFilteredProducts($this->siteId, [
            'category_ids' => [$category->id],
            'brand_ids' => [$brand->id],
            'min_price' => 25,
            'max_price' => 75
        ]);

        $productIds = $products['data']->pluck('id')->toArray();
        $this->assertContains($matchingProduct->id, $productIds);
        $this->assertNotContains($wrongCategory->id, $productIds);
        $this->assertNotContains($wrongPrice->id, $productIds);
    }

    public function test_get_filtered_products_applies_search_filter(): void
    {
        $matchingProduct = $this->createProduct([
            'name' => 'Samsung Galaxy Phone',
            'is_active' => true
        ]);

        $nonMatchingProduct = $this->createProduct([
            'name' => 'iPhone',
            'is_active' => true
        ]);

        $result = $this->repository->getFilteredProducts($this->siteId, [
            'q' => 'Samsung'
        ]);

        $productIds = array_column($result['data']->toArray(), 'id');
        $this->assertContains($matchingProduct->id, $productIds);
        $this->assertNotContains($nonMatchingProduct->id, $productIds);
    }

    public function test_get_filtered_products_handles_empty_category_ids(): void
    {
        $product = $this->createProduct(['is_active' => true]);

        $result = $this->repository->getFilteredProducts($this->siteId, [
            'category_ids' => ''
        ]);

        $this->assertGreaterThan(0, $result['pagination']['total']);
    }

    public function test_get_filtered_products_handles_empty_brand_ids(): void
    {
        $product = $this->createProduct(['is_active' => true]);

        $result = $this->repository->getFilteredProducts($this->siteId, [
            'brand_ids' => ''
        ]);

        $this->assertGreaterThan(0, $result['pagination']['total']);
    }

    public function test_get_filtered_products_applies_on_sale_filter(): void
    {
        $onSaleProduct = $this->createProduct([
            'is_active' => true,
            'price' => 100.00,
            'sale_price' => 75.00
        ]);

        $notOnSaleProduct = $this->createProduct([
            'is_active' => true,
            'price' => 100.00,
            'sale_price' => 0
        ]);

        $result = $this->repository->getFilteredProducts($this->siteId, [
            'on_sale' => '1'
        ]);

        $productIds = array_column($result['data']->toArray(), 'id');
        $this->assertContains($onSaleProduct->id, $productIds);
        $this->assertNotContains($notOnSaleProduct->id, $productIds);
    }

    public function test_get_filtered_products_paginates_correctly(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->createProduct(['is_active' => true]);
        }

        $result = $this->repository->getFilteredProducts($this->siteId, [
            'page' => 1,
            'per_page' => 10
        ]);

        $this->assertCount(10, $result['data']);
        $this->assertEquals(25, $result['pagination']['total']);
        $this->assertEquals(3, $result['pagination']['last_page']);
    }

    public function test_get_filtered_products_sorts_by_price_ascending(): void
    {
        $product1 = $this->createProduct([
            'is_active' => true,
            'price' => 100.00,
            'sale_price' => 80.00
        ]);

        $product2 = $this->createProduct([
            'is_active' => true,
            'price' => 50.00,
            'sale_price' => 40.00
        ]);

        $result = $this->repository->getFilteredProducts($this->siteId, [
            'sort_by' => 'price',
            'sort_order' => 'asc'
        ]);

        $prices = array_map(function ($p) {
            return $p->sale_price > 0 ? $p->sale_price : $p->price;
        }, $result['data']->toArray());

        $this->assertEquals(
            collect($prices)->sort()->all(),
            array_values($prices)
        );
    }

    public function test_get_filtered_products_returns_pagination_metadata(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->createProduct(['is_active' => true]);
        }

        $result = $this->repository->getFilteredProducts($this->siteId, [
            'page' => 2,
            'per_page' => 10
        ]);

        $this->assertEquals(2, $result['pagination']['current_page']);
        $this->assertEquals(10, $result['pagination']['per_page']);
        $this->assertEquals(15, $result['pagination']['total']);
        $this->assertEquals(2, $result['pagination']['last_page']);
    }
}