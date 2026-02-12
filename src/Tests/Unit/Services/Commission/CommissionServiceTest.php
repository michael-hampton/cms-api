<?php

namespace App\Tests\Unit\Services\Commission;

use App\Models\Merchant;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\ProductOfferBundle;
use App\Repositories\Product\ProductRepository;
use App\Services\Commission\CommissionService;
use Mockery;
use PHPUnit\Framework\TestCase;

class CommissionServiceTest extends TestCase
{
    private CommissionService $service;
    private ProductRepository $productRepository;

    public function testDetermineRateForBundle(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->is_bundle = true;
        $product->is_subscription = false;
        $product->id = 1;

        $bundle = Mockery::mock(ProductOfferBundle::class)->makePartial();

        $this->productRepository->shouldReceive('getBundlesForProduct')
            ->with(1)
            ->once()->andReturn(collect([$bundle]));

        $merchant = Mockery::mock(Merchant::class)->makePartial();

        $rate = $this->service->determineRate($product, $merchant);

        $this->assertEquals(0.08, $rate);
    }

    public function testDetermineRateForSubscription(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->is_bundle = false;
        $product->is_subscription = true;
        $product->id = 1;

        $bundle = Mockery::mock(ProductOfferBundle::class)->makePartial();

        $this->productRepository->shouldReceive('getBundlesForProduct')
            ->with(1)
            ->once()->andReturn(collect());

        $merchant = Mockery::mock(Merchant::class)->makePartial();

        $rate = $this->service->determineRate($product, $merchant);

        $this->assertEquals(0.05, $rate);
    }

    public function testDetermineRateForOffer(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->is_bundle = false;
        $product->is_subscription = false;
        $product->id = 1;

        $offer = Mockery::mock(ProductOffer::class)->makePartial();
        $offer->sale_price = 80;
        $offer->start_date = now_datetime()->subDays(1);
        $offer->end_date = now_datetime()->addDays(1);

        $this->productRepository->shouldReceive('getBundlesForProduct')
            ->with(1)
            ->once()->andReturn(collect());

        $this->productRepository->shouldReceive('getActiveOffersForProduct')
            ->with(1)
            ->once()->andReturn(collect([$offer]));

        $merchant = Mockery::mock(Merchant::class)->makePartial();

        $rate = $this->service->determineRate($product, $merchant);

        $this->assertEquals(0.12, $rate);
    }

    public function testDetermineRateForDeal(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->is_bundle = false;
        $product->id = 1;
        $product->is_subscription = false;
        $product->price = 100;
        $product->sale_price = 75;
        $product->offer_price = null;

        $this->productRepository->shouldReceive('getBundlesForProduct')
            ->with(1)
            ->once()->andReturn(collect());

        $this->productRepository->shouldReceive('getActiveOffersForProduct')
            ->with(1)
            ->once()->andReturn(collect());

        $merchant = Mockery::mock(Merchant::class)->makePartial();

        $rate = $this->service->determineRate($product, $merchant);

        $this->assertEquals(0.11, $rate);
    }

    public function testDetermineRateForDefault(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->is_bundle = false;
        $product->id = 1;
        $product->is_subscription = false;
        $product->price = 100;
        $product->sale_price = null;
        $product->offer_price = null;

        $this->productRepository->shouldReceive('getBundlesForProduct')
            ->with(1)
            ->once()->andReturn(collect());

        $this->productRepository->shouldReceive('getActiveOffersForProduct')
            ->with(1)
            ->once()->andReturn(collect());

        $merchant = Mockery::mock(Merchant::class)->makePartial();

        $rate = $this->service->determineRate($product, $merchant);

        $this->assertEquals(0.10, $rate);
    }

    public function testCalculateCommission(): void
    {
        $result = $this->service->calculate(100.00, 0.10);

        $this->assertEquals(0.10, $result['rate']);
        $this->assertEquals(10.00, $result['commission_amount']);
        $this->assertEquals(90.00, $result['net_amount']);
    }

    public function testStrategyPriorityBundleOverOffer(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->is_bundle = true;
        $product->is_subscription = false;
        $product->id = 1;

        $offer = Mockery::mock(ProductOffer::class)->makePartial();
        $offer->sale_price = 80;
        $offer->start_date = now_datetime()->subDays(1);
        $offer->end_date = now_datetime()->addDays(1);

        $bundle = Mockery::mock(ProductOfferBundle::class)->makePartial();

        $product->price = 100;

        $this->productRepository->shouldReceive('getBundlesForProduct')
            ->with(1)
            ->once()->andReturn(collect([$bundle]));

        $merchant = Mockery::mock(Merchant::class)->makePartial();

        $rate = $this->service->determineRate($product, $merchant);

        // Bundle takes priority over offer
        $this->assertEquals(0.08, $rate);
    }

    public function testStrategyPrioritySubscriptionOverDeal(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->is_bundle = false;
        $product->is_subscription = true;
        $product->price = 100;
        $product->sale_price = 75;
        $product->id = 1;

        $this->productRepository->shouldReceive('getBundlesForProduct')
            ->with(1)
            ->once()->andReturn(collect());

        $merchant = Mockery::mock(Merchant::class)->makePartial();

        $rate = $this->service->determineRate($product, $merchant);

        // Subscription takes priority over deal
        $this->assertEquals(0.05, $rate);
    }

    public function testCalculateRoundsProperly(): void
    {
        $result = $this->service->calculate(99.99, 0.0725);

        $this->assertEquals(7.25, $result['commission_amount']);
        $this->assertEquals(92.74, $result['net_amount']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $config = include __DIR__ . '/../../../config/commission.php';
        $this->productRepository = Mockery::mock(ProductRepository::class);
        $this->service = new CommissionService(
            $this->productRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}