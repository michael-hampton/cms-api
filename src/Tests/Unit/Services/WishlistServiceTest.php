<?php

namespace App\Tests\Unit\Services;

use App\Models\Product;
use App\Models\Wishlist;
use App\Repositories\Members\WishlistRepository;
use App\Repositories\Product\ProductRepository;
use App\Services\Members\WishlistService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class WishlistServiceTest extends FunctionalTestCase
{
    protected $wishlistRepository;
    protected $productRepository;
    protected WishlistService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wishlistRepository = Mockery::mock(WishlistRepository::class);
        $this->productRepository = Mockery::mock(ProductRepository::class);

        $this->service = new WishlistService($this->wishlistRepository, $this->productRepository);

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
}