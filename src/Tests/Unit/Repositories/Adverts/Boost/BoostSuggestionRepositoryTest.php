<?php

namespace App\Tests\Unit\Repositories\Adverts\Boost;

use App\Models\Boost;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductImpression;
use App\Models\Review;
use App\Repositories\Adverts\Boost\BoostSuggestionRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class BoostSuggestionRepositoryTest extends FunctionalTestCase
{
    use CreatesTestData;

    private BoostSuggestionRepository $repository;

    public function test_get_active_merchant_products_returns_in_stock_active(): void
    {
        $product1 = $this->createProduct(['merchant_id' => 1, 'is_active' => true, 'stock_quantity' => 10]);
        $product2 = $this->createProduct(['merchant_id' => 1, 'is_active' => false, 'stock_quantity' => 10]);
        $product3 = $this->createProduct(['merchant_id' => 1, 'is_active' => true, 'stock_quantity' => 0]);

        $merchant = $this->createMerchant();

        $this->createProductMerchant($product1->id, ['merchant_id' => $merchant->id]);
        $this->createProductMerchant($product2->id, ['merchant_id' => $merchant->id]);
        $this->createProductMerchant($product3->id, ['merchant_id' => $merchant->id]);

        $results = $this->repository->getActiveMerchantProducts(1);

        $this->assertCount(1, $results);
    }

    public function test_get_impression_counts_returns_correct_totals(): void
    {
        $product = $this->createProduct();
        $product2 = $this->createProduct();

        ProductImpression::create(['product_id' => $product->id, 'context' => 'listing', 'viewed_at' => now()]);
        ProductImpression::create(['product_id' => $product->id, 'context' => 'listing', 'viewed_at' => now()]);
        ProductImpression::create(['product_id' => $product2->id, 'context' => 'deals', 'viewed_at' => now()]);
        // Outside window
        ProductImpression::create(['product_id' => $product->id, 'context' => 'listing', 'viewed_at' => now_datetime()->modify('-60 days')]);

        $counts = $this->repository->getImpressionCountsForProducts([$product->id, $product2->id], 30);

        $this->assertEquals($product2->id, $counts[1]);
        $this->assertEquals($product->id, $counts[2]);
    }

    public function test_get_units_sold_counts_completed_orders_only(): void
    {
        $completedOrder = $this->createOrder(['status' => 'completed', 'created_at' => now()]);
        $cancelledOrder = $this->createOrder(['status' => 'cancelled', 'created_at' => now()]);

        $product = $this->createProduct();

        OrderItem::create(['subtotal' => 22, 'order_id' => $completedOrder->id, 'product_id' => $product->id, 'product_name' => $product->name, 'quantity' => 3, 'unit_price' => 10, 'total' => 30]);
        OrderItem::create(['subtotal' => 22, 'order_id' => $cancelledOrder->id, 'product_id' => $product->id, 'product_name' => $product->name, 'quantity' => 5, 'unit_price' => 10, 'total' => 50]);

        $sold = $this->repository->getUnitsSoldForProducts([$product->id], 30);

        $this->assertEquals(3, $sold[$product->id]);
    }

    public function test_get_units_sold_excludes_old_orders(): void
    {
        $oldOrder = $this->createOrder(['status' => 'completed']);

        // Force the timestamp to be in the past after creation
        // because Eloquent ignores created_at overrides when timestamps = true
        Order::where('id', $oldOrder->id)->update(['created_at' => now_datetime()->modify('-60 days')->format('Y-m-d H:i:s')]);

        $product = $this->createProduct();

        OrderItem::create([
            'order_id' => $oldOrder->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 10,
            'total' => 100,
            'product_name' => 'Test',
            'subtotal' => 22
        ]);

        $sold = $this->repository->getUnitsSoldForProducts([1], 30);

        $this->assertEquals(0, $sold[1] ?? 0);
    }

    public function test_get_average_ratings_returns_approved_reviews_only(): void
    {
        $product = $this->createProduct();
        Review::create(['product_id' => $product->id, 'rating' => 5, 'is_approved' => true, 'site_id' => $this->siteId]);
        Review::create(['product_id' => $product->id, 'rating' => 3, 'is_approved' => true, 'site_id' => $this->siteId]);
        Review::create(['product_id' => $product->id, 'rating' => 1, 'is_approved' => false, 'site_id' => $this->siteId]);

        $ratings = $this->repository->getAverageRatingsForProducts([1]);

        $this->assertEquals(4.0, $ratings[1]);
    }

    public function test_get_active_boosts_for_merchant_returns_active_only(): void
    {
        Boost::create([
            'merchant_id' => 1, 'boostable_type' => 'product', 'boostable_id' => 1,
            'context' => 'listing', 'status' => 'active', 'multiplier' => 1.5,
            'price_paid' => 35.00, 'currency' => 'GBP',
            'starts_at' => '2026-01-01', 'ends_at' => '2026-01-08',
        ]);
        Boost::create([
            'merchant_id' => 1, 'boostable_type' => 'product', 'boostable_id' => 2,
            'context' => 'listing', 'status' => 'expired', 'multiplier' => 1.5,
            'price_paid' => 35.00, 'currency' => 'GBP',
            'starts_at' => '2026-01-01', 'ends_at' => '2026-01-08',
        ]);

        $boosts = $this->repository->getActiveBoostsForMerchant(1);

        $this->assertCount(1, $boosts);
        $this->assertEquals('active', $boosts->first()->status);
    }

    public function test_get_active_boosts_scoped_to_product_ids(): void
    {
        Boost::create([
            'merchant_id' => 1, 'boostable_type' => 'product', 'boostable_id' => 10,
            'context' => 'listing', 'status' => 'active', 'multiplier' => 1.5,
            'price_paid' => 35.00, 'currency' => 'GBP',
            'starts_at' => '2026-01-01', 'ends_at' => '2026-01-08',
        ]);

        $boosts = $this->repository->getActiveBoostsForMerchant(1, [99]);

        $this->assertCount(0, $boosts);
    }

    public function test_get_active_offers_returns_only_current(): void
    {
        $activeProduct = $this->createProduct(['is_active' => true]);
        $expiredProduct = $this->createProduct(['is_active' => true]);

        $this->createProductOffer($activeProduct->id, [
            'product_id' => $activeProduct->id,
            'is_active' => true,
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
        ]);
        $this->createProductOffer($expiredProduct->id, [
            'product_id' => $expiredProduct->id,
            'is_active' => true,
            'start_date' => date('Y-m-d H:i:s', strtotime('-30 days')),
            'end_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);

        $offers = $this->repository->getActiveOffersForProducts([$activeProduct->id, $expiredProduct->id]);

        $this->assertArrayHasKey($activeProduct->id, $offers);
        $this->assertArrayNotHasKey($expiredProduct->id, $offers);
    }

    public function test_returns_empty_arrays_for_empty_product_id_input(): void
    {
        $this->assertEmpty($this->repository->getImpressionCountsForProducts([]));
        $this->assertEmpty($this->repository->getUnitsSoldForProducts([]));
        $this->assertEmpty($this->repository->getActiveOffersForProducts([]));
        $this->assertEmpty($this->repository->getAverageRatingsForProducts([]));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new BoostSuggestionRepository();
    }
}