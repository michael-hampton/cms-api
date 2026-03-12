<?php

namespace App\Tests\Unit\Services\Shopping;

use App\Models\Product;
use App\Models\ProductOfferBundle;
use App\Models\Wishlist;
use App\Repositories\Offers\ProductOfferBundleRepository;
use App\Repositories\Offers\ProductOfferRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Shopping\WishlistRepository;
use App\Services\Shopping\WishlistService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery;

class WishlistServiceTest extends FunctionalTestCase
{
    use CreatesTestData;
    protected $wishlistRepository;
    protected $productRepository;
    protected WishlistService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wishlistRepository = Mockery::mock(WishlistRepository::class);
        $this->productRepository = Mockery::mock(ProductRepository::class);
        $this->productOfferBundleRepository = Mockery::mock(ProductOfferBundleRepository::class);
        $this->productOfferRepository = Mockery::mock(ProductOfferRepository::class);

        $this->service = new WishlistService(
            $this->wishlistRepository,
            $this->productRepository,
            $this->productOfferBundleRepository,
            $this->productOfferRepository
        );

        $_SESSION['wishlist_session_id'] = 'test_wishlist_123';
    }

    protected function tearDown(): void
    {
        // Clean up session
        unset($_SESSION['wishlist_session_id']);

        Mockery::close();
        parent::tearDown();
    }

    public function testGetItemsReturnsEmptyArrayWhenNoItems()
    {
        $this->wishlistRepository->shouldReceive('findBySessionOrUser')
            ->once()
            ->andReturn(collect([]));

        $result = $this->service->getItems();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetItemsReturnsFormattedItems()
    {
        $product = new Product([
            'id' => 1,
            'name' => 'Test Product',
            'slug' => 'test-product',
            'price' => 99.99,
            'sale_price' => 79.99,
            'discount_percentage' => 20,
            'in_stock' => true,
            'image_url' => '/images/test.jpg'
        ]);

        $wishlistItem = new Wishlist([
            'id' => 1,
            'product_id' => 1
        ]);
        $wishlistItem->setRelation('product', $product);

        $this->wishlistRepository->shouldReceive('findBySessionOrUser')
            ->once()
            ->andReturn(collect([$wishlistItem]));

        $result = $this->service->getItems();

        $this->assertCount(1, $result);
        $this->assertEquals('Test Product', $result[0]['product_name']);
        $this->assertEquals(79.99, $result[0]['price']);
        $this->assertEquals(20, $result[0]['discount_percentage']);
    }

    public function testAddItemSuccessfully()
    {
        $product = new Product([
            'id' => 1,
            'name' => 'Test Product',
            'site_id' => 1
        ]);

        $this->productRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($product);

        $this->wishlistRepository->shouldReceive('existsByProduct')
            ->once()
            ->andReturn(false);

        $this->wishlistRepository->shouldReceive('create')
            ->once()
            ->andReturn(new Wishlist());

        $result = $this->service->addItem(1);

        $this->assertTrue($result['success']);
        $this->assertEquals('Product added to wishlist', $result['message']);
    }

    public function testAddItemFailsWhenProductNotFound()
    {
        $this->productRepository->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $result = $this->service->addItem(999);

        $this->assertFalse($result['success']);
        $this->assertEquals('Product not found', $result['message']);
    }

    public function testAddItemFailsWhenAlreadyInWishlist()
    {
        $product = new Product(['id' => 1, 'name' => 'Test Product']);

        $this->productRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($product);

        $this->wishlistRepository->shouldReceive('existsByProduct')
            ->once()
            ->andReturn(true);

        $result = $this->service->addItem(1);

        $this->assertFalse($result['success']);
        $this->assertEquals('Product already in wishlist', $result['message']);
    }

    public function testRemoveItemSuccessfully()
    {
        $this->wishlistRepository->shouldReceive('deleteByProduct')
            ->with(1, null, 'test_wishlist_123')  // Changed to expect null for userId
            ->once()
            ->andReturn(true);

        $result = $this->service->removeItem(1);

        $this->assertTrue($result['success']);
        $this->assertEquals('Item removed from wishlist', $result['message']);
    }

    public function testRemoveItemFailsWhenNotFound()
    {
        $this->wishlistRepository->shouldReceive('deleteByProduct')
            ->once()
            ->andReturn(false);

        $result = $this->service->removeItem(999);

        $this->assertFalse($result['success']);
        $this->assertEquals('Item not found in wishlist', $result['message']);
    }

    public function testIsInWishlistReturnsTrue()
    {
        $product = new Product(['id' => 1]);
        $user = null;

        $this->wishlistRepository->shouldReceive('existsByProduct')
            ->with(1, null, 'test_wishlist_123')
            ->once()
            ->andReturn(true);

        $result = $this->service->isInWishlist($user, $product);

        $this->assertTrue($result);
    }

    public function testIsInWishlistReturnsFalse()
    {
        $product = new Product(['id' => 1]);
        $user = null;

        $this->wishlistRepository->shouldReceive('existsByProduct')
            ->with(1, null, 'test_wishlist_123')
            ->once()
            ->andReturn(false);

        $result = $this->service->isInWishlist($user, $product);

        $this->assertFalse($result);
    }

    public function testGetCountReturnsCorrectCount()
    {
        $this->wishlistRepository->shouldReceive('getCountBySessionOrUser')
            ->with(null, 'test_wishlist_123')  // Expect null for userId
            ->once()
            ->andReturn(3);

        $count = $this->service->getCount();

        $this->assertEquals(3, $count);
    }

    public function testAddOfferSuccessfully()
    {
        $product = $this->createProduct();
        $offer = $this->createProductOffer($product->id);

        $this->productOfferRepository->shouldReceive('find')
            ->with($offer->id)
            ->once()
            ->andReturn($offer);

        $this->wishlistRepository->shouldReceive('getOffers')
            ->once()
            ->andReturn(collect([]));

        $this->wishlistRepository->shouldReceive('create')
            ->once()
            ->andReturn(new Wishlist());

        $result = $this->service->addOffer($offer->id);

        $this->assertTrue($result['success']);
        $this->assertEquals('Offer added to wishlist', $result['message']);
    }

    public function testAddOfferFailsWhenOfferNotAvailable()
    {
        $product = $this->createProduct();
        $offer = $this->createProductOffer($product->id, [
            'is_active' => false
        ]);

        $this->productOfferRepository->shouldReceive('find')
            ->with($offer->id)
            ->once()
            ->andReturn($offer);

        $result = $this->service->addOffer($offer->id);

        $this->assertFalse($result['success']);
        $this->assertEquals('Offer not available', $result['message']);
    }

    public function testAddOfferFailsWhenAlreadyInWishlist()
    {
        $product = $this->createProduct();
        $offer = $this->createProductOffer($product->id);

        $existingWishlist = Mockery::mock(Wishlist::class);

        $this->productOfferRepository->shouldReceive('find')
            ->with($offer->id)
            ->once()
            ->andReturn($offer);

        $this->wishlistRepository->shouldReceive('getOffers')
            ->once()
            ->andReturn(collect([$existingWishlist]));

        $result = $this->service->addOffer($offer->id);

        $this->assertFalse($result['success']);
        $this->assertEquals('Offer already in wishlist', $result['message']);
    }

    public function testAddBundleSuccessfully()
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();
        $offer1 = $this->createProductOffer($product1->id);
        $offer2 = $this->createProductOffer($product2->id);

        $bundle = ProductOfferBundle::create([
            'name' => 'Test Bundle',
            'slug' => 'test-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'discount_percentage' => 25,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
            'site_id' => $this->siteId
        ]);

        $this->productOfferBundleRepository->shouldReceive('find')
            ->with($bundle->id)
            ->once()
            ->andReturn($bundle);

        $this->wishlistRepository->shouldReceive('getBundles')
            ->once()
            ->andReturn(collect([]));

        $this->wishlistRepository->shouldReceive('create')
            ->once()
            ->andReturn(new Wishlist());

        $result = $this->service->addBundle($bundle->id);

        $this->assertTrue($result['success']);
        $this->assertEquals('Bundle added to wishlist', $result['message']);
    }

    public function testAddBundleFailsWhenBundleNotAvailable()
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();

        $bundle = ProductOfferBundle::create([
            'name' => 'Test Bundle',
            'slug' => 'test-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'discount_percentage' => 25,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => false,
            'site_id' => $this->siteId
        ]);

        $this->productOfferBundleRepository->shouldReceive('find')
            ->with($bundle->id)
            ->once()
            ->andReturn($bundle);

        $result = $this->service->addBundle($bundle->id);

        $this->assertFalse($result['success']);
        $this->assertEquals('Bundle not available', $result['message']);
    }

    public function testAddBundleFailsWhenAlreadyInWishlist()
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();

        $bundle = ProductOfferBundle::create([
            'name' => 'Test Bundle',
            'slug' => 'test-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'discount_percentage' => 25,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
            'site_id' => $this->siteId
        ]);

        $existingWishlist = Mockery::mock(Wishlist::class);

        $this->productOfferBundleRepository->shouldReceive('find')
            ->with($bundle->id)
            ->once()
            ->andReturn($bundle);

        $this->wishlistRepository->shouldReceive('getBundles')
            ->once()
            ->andReturn(collect([$existingWishlist]));

        $result = $this->service->addBundle($bundle->id);

        $this->assertFalse($result['success']);
        $this->assertEquals('Bundle already in wishlist', $result['message']);
    }

}