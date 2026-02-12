<?php

namespace App\Tests\Unit\Services\Commission;

use App\Models\Merchant;
use App\Models\Product;
use App\Repositories\Product\ProductRepository;
use App\Services\Commission\CommissionService;
use Mockery;
use PHPUnit\Framework\TestCase;

class CommissionStrategyPriorityTest extends TestCase
{
    private CommissionService $service;
    private ProductRepository $productRepository;

    /**
     * Test that bundle rate applies even when product also qualifies as offer
     */
    public function testBundleTakesPriorityOverOffer(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->is_subscription = true; // Would trigger Subscription (0.05)
        $product->price = 100;
        $product->sale_price = 80;        // Would trigger Deal (0.11)

        // Repository returns a bundle
        $this->productRepository->shouldReceive('getBundlesForProduct')
            ->with(1)->andReturn(collect(['bundle_item']));

        $merchant = Mockery::mock(Merchant::class)->makePartial();

        // Result should be Bundle rate (0.08)
        $this->assertEquals(0.08, $this->service->determineRate($product, $merchant));
    }

    public function testSubscriptionPriority(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 2;
        $product->is_subscription = true;
        $product->price = 100;
        $product->sale_price = 80; // Would trigger Deal

        // NOT a bundle
        $this->productRepository->shouldReceive('getBundlesForProduct')
            ->with(2)->andReturn(collect());
        // WOULD be an offer
        $this->productRepository->shouldReceive('getActiveOffersForProduct')
            ->with(2)->andReturn(collect(['offer_item']));

        $merchant = Mockery::mock(Merchant::class)->makePartial();

        // Result should be Subscription rate (0.05)
        $this->assertEquals(0.05, $this->service->determineRate($product, $merchant));
    }

    public function testOfferPriority(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 3;
        $product->is_subscription = false;
        $product->price = 100;
        $product->sale_price = 80; // Would trigger Deal

        $this->productRepository->shouldReceive('getBundlesForProduct')
            ->with(3)->andReturn(collect());
        $this->productRepository->shouldReceive('getActiveOffersForProduct')
            ->with(3)->andReturn(collect(['offer_item']));

        $merchant = Mockery::mock(Merchant::class)->makePartial();

        // Result should be Offer rate (0.12)
        $this->assertEquals(0.12, $this->service->determineRate($product, $merchant));
    }

    /**
     * Test Deal priority over Default.
     */
    public function testDealPriority(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 4;
        $product->is_subscription = false;
        $product->price = 100;
        $product->sale_price = 80;

        $this->productRepository->shouldReceive('getBundlesForProduct')
            ->with(4)->andReturn(collect());
        $this->productRepository->shouldReceive('getActiveOffersForProduct')
            ->with(4)->andReturn(collect());

        $merchant = Mockery::mock(Merchant::class)->makePartial();

        // Result should be Deal rate (0.11)
        $this->assertEquals(0.11, $this->service->determineRate($product, $merchant));
    }

    /**
     * Test full priority chain
     */
    public function testFullPriorityChain(): void
    {
        $merchant = Mockery::mock(Merchant::class)->makePartial();

        // 1. Bundle wins over everything (Highest Priority)
        $bundleProduct = Mockery::mock(Product::class)->makePartial();
        $bundleProduct->id = 101; // ID required for Repository call
        $bundleProduct->is_subscription = true;
        $bundleProduct->price = 100;
        $bundleProduct->sale_price = 75;

        // Trigger Bundle Strategy via Repository
        $this->productRepository->shouldReceive('getBundlesForProduct')
            ->with(101)
            ->andReturn(collect(['bundle_info']));

        $this->assertEquals(0.08, $this->service->determineRate($bundleProduct, $merchant));

        // 2. Subscription wins over offer/deal/default
        $subscriptionProduct = Mockery::mock(Product::class)->makePartial();
        $subscriptionProduct->id = 102;
        $subscriptionProduct->is_subscription = true;
        $subscriptionProduct->price = 100;
        $subscriptionProduct->sale_price = 75;

        // Fail Bundle check, but "Offer" logic would still be true
        $this->productRepository->shouldReceive('getBundlesForProduct')
            ->with(102)->andReturn(collect());
        $this->productRepository->shouldReceive('getActiveOffersForProduct')
            ->with(102)->andReturn(collect(['offer_info']));

        $this->assertEquals(0.05, $this->service->determineRate($subscriptionProduct, $merchant));

        // 3. Offer wins over deal/default
        $offerProduct = Mockery::mock(Product::class)->makePartial();
        $offerProduct->id = 103;
        $offerProduct->is_subscription = false;
        $offerProduct->price = 100;
        $offerProduct->sale_price = 75;

        // Fail Bundle and Subscription, but trigger Offer via Repository
        $this->productRepository->shouldReceive('getBundlesForProduct')
            ->with(103)->andReturn(collect());
        $this->productRepository->shouldReceive('getActiveOffersForProduct')
            ->with(103)->andReturn(collect(['offer_info']));

        $this->assertEquals(0.12, $this->service->determineRate($offerProduct, $merchant));

        // 4. Deal wins over default
        $dealProduct = Mockery::mock(Product::class)->makePartial();
        $dealProduct->id = 104;
        $dealProduct->is_subscription = false;
        $dealProduct->price = 100;
        $dealProduct->sale_price = 75; // Triggers Deal strategy logic

        // Fail Bundle and Offer checks
        $this->productRepository->shouldReceive('getBundlesForProduct')
            ->with(104)->andReturn(collect());
        $this->productRepository->shouldReceive('getActiveOffersForProduct')
            ->with(104)->andReturn(collect());

        $this->assertEquals(0.11, $this->service->determineRate($dealProduct, $merchant));

        // 5. Default when nothing else matches
        $normalProduct = Mockery::mock(Product::class)->makePartial();
        $normalProduct->id = 105;
        $normalProduct->is_subscription = false;
        $normalProduct->price = 100;
        $normalProduct->sale_price = null;

        $this->productRepository->shouldReceive('getBundlesForProduct')
            ->with(105)->andReturn(collect());
        $this->productRepository->shouldReceive('getActiveOffersForProduct')
            ->with(105)->andReturn(collect());

        $this->assertEquals(0.10, $this->service->determineRate($normalProduct, $merchant));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->productRepository = Mockery::mock(ProductRepository::class);

        $this->service = new CommissionService($this->productRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}