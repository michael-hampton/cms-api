<?php

namespace App\Tests\Unit\Repositories\Product;

use App\Models\Product;
use App\Models\ProductView;
use App\Repositories\Product\ProductViewRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery;

class ProductViewRepositoryTest extends FunctionalTestCase
{
    use CreatesTestData;

    private ProductViewRepository $repository;

    public function testTrackViewCreatesNewRecord(): void
    {
        $product = $this->createProduct();

        $member = $this->createMember();

        $sessionId = 'session123';
        $ipAddress = '192.168.1.1';

        $result = $this->repository->trackView($product, $member->id, $sessionId, $ipAddress);

        $this->assertInstanceOf(ProductView::class, $result);
        $this->assertEquals($product->id, $result->product_id);
        $this->assertEquals($this->siteId, $result->site_id);
        $this->assertEquals($member->id, $result->user_id);
        $this->assertEquals($sessionId, $result->session_id);
        $this->assertEquals($ipAddress, $result->ip_address);
        $this->assertNotNull($result->viewed_at);
    }

    public function testTrackViewWithNullUserId(): void
    {
        $product = $this->createProduct();

        $result = $this->repository->trackView($product, null, 'session456', '10.0.0.1');

        $this->assertInstanceOf(ProductView::class, $result);
        $this->assertNull($result->user_id);
        $this->assertEquals('session456', $result->session_id);
    }

    public function testGetViewedProductIdsByMemberReturnsArray(): void
    {
        $member = $this->createMember();
        $product = $this->createProduct();

        // Create test data
        $this->createProductView(['user_id' => $member->id, 'product_id' => $product->id, 'viewed_at' => now_datetime()->subDays(1)]);
        $this->createProductView(['user_id' => $member->id, 'viewed_at' => now_datetime()->subDays(2)->format('Y-m-d H:i:s')]);
        $this->createProductView(['user_id' => $member->id, 'product_id' => $product->id, 'viewed_at' => now_datetime()->subDays(3)]);
        $this->createProductView(['user_id' => $member->id, 'viewed_at' => now_datetime()->subDays(4)->format('Y-m-d H:i:s')]);

        $result = $this->repository->getViewedProductIdsByMember($member->id, 10, 30);

        $this->assertIsArray($result);
        $this->assertCount(3, $result); // Should remove duplicates
        $this->assertContains($product->id, $result); // Most recent first
    }

    public function testGetViewedProductIdsByMemberRespectsLimit(): void
    {
        $member = $this->createMember();

        for ($i = 1; $i <= 10; $i++) {
            $this->createProductView(['user_id' => $member->id, 'viewed_at' => now_datetime()->subDays($i)]);
        }

        $result = $this->repository->getViewedProductIdsByMember($member->id, 5, 30);

        $this->assertCount(5, $result);
    }

    public function testGetViewedProductIdsByMemberRespectsDaysBack(): void
    {
        $member = $this->createMember();
        $member2 = $this->createMember();
        $product = $this->createProduct();

        // Create test data
        $this->createProductView(['product_id' => $product->id, 'user_id' => $member->id, 'viewed_at' => now_datetime()->subDays(5)]);
        $this->createProductView(['product_id' => $product->id, 'user_id' => $member2->id, 'viewed_at' => now_datetime()->subDays(35)]);

        $result = $this->repository->getViewedProductIdsByMember($member->id, 10, 30);

        $this->assertCount(1, $result);
        $this->assertEquals([$product->id], $result);
    }

    public function testGetViewedProductsByMemberReturnsCollection(): void
    {
        $member = $this->createMember();
        $product = $this->createProduct();
        $product2 = $this->createProduct();

        // Create test data
        $this->createProductView(['product_id' => $product->id, 'user_id' => $member->id, 'viewed_at' => now_datetime()->subDays(1)]);
        $this->createProductView(['product_id' => $product2->id, 'user_id' => $member->id, 'viewed_at' => now_datetime()->subDays(2)]);

        $result = $this->repository->getViewedProductsByMember($member->id, 10, 30);

        $this->assertInstanceOf(\App\Framework\Support\Collection::class, $result);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(Product::class, $result->first());
    }

    public function testGetViewedProductsByMemberReturnsEmptyWhenNoViews(): void
    {
        $result = $this->repository->getViewedProductsByMember(999, 10, 30);

        $this->assertInstanceOf(\App\Framework\Support\Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    public function testGetProductViewCountReturnsTotal(): void
    {

        $member = $this->createMember();
        $product = $this->createProduct();

        // Create test data
        $this->createProductView(['user_id' => $member->id, 'product_id' => $product->id, 'viewed_at' => now_datetime()->subDays(1)]);
        $this->createProductView(['user_id' => $member->id, 'product_id' => $product->id, 'viewed_at' => now_datetime()->subDays(2)]);
        $this->createProductView(['user_id' => $member->id, 'product_id' => $product->id, 'viewed_at' => now_datetime()->subDays(3)]);

        $result = $this->repository->getProductViewCount($product->id);

        $this->assertEquals(3, $result);
    }

    public function testGetProductViewCountWithDaysBack(): void
    {
        $member = $this->createMember();
        $product = $this->createProduct();

        // Create test data
        $this->createProductView(['user_id' => $member->id, 'product_id' => $product->id, 'viewed_at' => now_datetime()->subDays(5)]);
        $this->createProductView(['user_id' => $member->id, 'product_id' => $product->id, 'viewed_at' => now_datetime()->subDays(60)]);


        $result = $this->repository->getProductViewCount($product->id, 30);

        $this->assertEquals(1, $result);
    }

    public function testGetProductUniqueViewCountExcludesNullUsers(): void
    {
        $member = $this->createMember();
        $product = $this->createProduct();

        // Create test data
        $this->createProductView(['user_id' => $member->id, 'product_id' => $product->id, 'viewed_at' => now()]);
        $this->createProductView(['user_id' => $member->id, 'product_id' => $product->id, 'viewed_at' => now_datetime()->subHours(1)->format('Y-m-d H:i:s')]);
        $this->createProductView(['user_id' => null, 'product_id' => $product->id, 'viewed_at' => now()]);
        $this->createProductView();

        $result = $this->repository->getProductUniqueViewCount($product->id);

        $this->assertEquals(2, $result); // Only unique user IDs
    }

    public function testGetMostViewedProductsReturnsTopProducts(): void
    {
        $siteId = 1;
        $product1 = $this->createProduct();

        // Product 1: 5 views
        for ($i = 1; $i <= 5; $i++) {
            $member = $this->createMember();

            // Create test data
            $this->createProductView(['user_id' => $member->id, 'product_id' => $product1->id, 'viewed_at' => now()]);
        }

        $product2 = $this->createProduct();

        // Product 2: 3 views
        for ($i = 1; $i <= 3; $i++) {
            $member = $this->createMember();

            // Create test data
            $this->createProductView(['user_id' => $member->id, 'product_id' => $product2->id, 'viewed_at' => now()]);
        }

        $product3 = $this->createProduct();

        // Product 3: 7 views
        for ($i = 1; $i <= 7; $i++) {
            $member = $this->createMember();

            // Create test data
            $this->createProductView(['user_id' => $member->id, 'product_id' => $product3->id, 'viewed_at' => now()]);
        }

        $result = $this->repository->getMostViewedProducts($siteId, 2);

        $this->assertCount(2, $result);
        $this->assertEquals($product3->id, $result->first()['product_id']); // Most viewed first
        $this->assertEquals(7, $result->first()['view_count']);
        $this->assertEquals($product1->id, $result->last()['product_id']);
        $this->assertEquals(5, $result->last()['view_count']);
    }

    public function testHasRecentViewReturnsTrueForRecentView(): void
    {
        $member = $this->createMember();
        $product = $this->createProduct();

        // Create test data
        $this->createProductView(['user_id' => $member->id, 'product_id' => $product->id, 'viewed_at' => now_datetime()->subMinutes(30)]);

        $result = $this->repository->hasRecentView($product->id, $member->id, 60);

        $this->assertTrue($result);
    }

    public function testHasRecentViewReturnsFalseForOldView(): void
    {
        $member = $this->createMember();
        $product = $this->createProduct();

        // Create test data
        $this->createProductView(['user_id' => $member->id, 'product_id' => $product->id, 'viewed_at' => now_datetime()->subMinutes(90)->format('Y-m-d H:i:s')]);

        $result = $this->repository->hasRecentView($product->id, $member->id, 60);

        $this->assertFalse($result);
    }

    public function testGetMemberTotalViewsReturnsTotalCount(): void
    {
        $member = $this->createMember();
        $product = $this->createProduct();

        // Create test data
        $this->createProductView(['user_id' => $member->id, 'product_id' => $product->id, 'viewed_at' => now()]);
        $this->createProductView(['user_id' => $member->id, 'product_id' => $product->id, 'viewed_at' => now()]);
        $this->createProductView(['user_id' => $member->id, 'product_id' => $product->id, 'viewed_at' => now()]);

        $result = $this->repository->getMemberTotalViews($member->id);

        $this->assertEquals(3, $result);
    }

    public function testGetMemberTotalViewsWithDaysBack(): void
    {
        $member = $this->createMember();
        $product = $this->createProduct();

        // Create test data
        $this->createProductView(['user_id' => $member->id, 'product_id' => $product->id, 'viewed_at' => now_datetime()->subDays(30)]);
        $this->createProductView(['user_id' => $member->id, 'product_id' => $product->id, 'viewed_at' => now_datetime()->subDays(35)]);

        $result = $this->repository->getMemberTotalViews($member->id, 30);

        $this->assertEquals(1, $result);
    }

    public function testGetProductViewStatsReturnsCompleteStats(): void
    {
        $member = $this->createMember();
        $member2 = $this->createMember();
        $product = $this->createProduct();

        // Create test data
        $this->createProductView(['user_id' => $member->id, 'product_id' => $product->id, 'viewed_at' => now()]);
        $this->createProductView(['user_id' => $member2->id, 'product_id' => $product->id, 'viewed_at' => now()]);
        $this->createProductView(['user_id' => $member->id, 'product_id' => $product->id, 'viewed_at' => now()]);
        $this->createProductView(['user_id' => null, 'product_id' => $product->id, 'viewed_at' => now()]);

        $result = $this->repository->getProductViewStats($product->id, 30);

        $this->assertIsArray($result);
        $this->assertEquals(4, $result['total_views']);
        $this->assertEquals(2, $result['unique_users']);
        $this->assertEquals(1, $result['anonymous_views']);
        $this->assertArrayHasKey('unique_sessions', $result);
    }

    public function testDeleteOldViewsRemovesOldRecords(): void
    {
        $member = $this->createMember();
        $member2 = $this->createMember();
        $product = $this->createProduct();
        $product2 = $this->createProduct();

        // Create test data
        $this->createProductView(['user_id' => $member->id, 'product_id' => $product->id, 'viewed_at' => now_datetime()->subDays(400)]);
        $this->createProductView(['user_id' => $member2->id, 'product_id' => $product2->id, 'viewed_at' => now_datetime()->subDays(100)]);

        $deleted = $this->repository->deleteOldViews(365);

        $this->assertEquals(1, $deleted);
    }

    public function testGetFrequentlyViewedWithReturnsRelatedProducts(): void
    {
        $member = $this->createMember();
        $member2 = $this->createMember();
        $product = $this->createProduct();
        $product2 = $this->createProduct();
        $product3 = $this->createProduct();

        // Create test data
        $this->createProductView(['user_id' => $member->id, 'product_id' => $product->id, 'viewed_at' => now()]);
        $this->createProductView(['user_id' => $member->id, 'product_id' => $product2->id, 'viewed_at' => now()]);

        $this->createProductView(['user_id' => $member2->id, 'product_id' => $product->id, 'viewed_at' => now()]);
        $this->createProductView(['user_id' => $member2->id, 'product_id' => $product2->id, 'viewed_at' => now()]);

        $this->createProductView(['user_id' => $member2->id, 'product_id' => $product3->id, 'viewed_at' => now()]);

        $result = $this->repository->getFrequentlyViewedWith($product->id, 5, 90);

        $this->assertIsArray($result);
        $this->assertContains($product2->id, $result); // Most frequently viewed with
        $this->assertNotContains($product->id, $result); // Shouldn't include itself
    }

    public function testGetFrequentlyViewedWithReturnsEmptyForNewProduct(): void
    {
        $result = $this->repository->getFrequentlyViewedWith(9999, 5, 90);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ProductViewRepository();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}