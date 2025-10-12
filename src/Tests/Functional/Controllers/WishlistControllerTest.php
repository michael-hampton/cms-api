<?php

namespace App\Tests\Functional\Controllers;

use App\Framework\Authorization\Auth;
use App\Models\Product;

class WishlistControllerTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanupDatabase();
        $this->ensureSiteExists();
    }

    public function testIndexReturnsEmptyWishlist()
    {
        $response = $this->getForSite('/api/wishlist');

        $this->assertResponseOk($response);
        $this->assertJsonResponse($response);

        $data = json_decode($response->getContent(), true);
        $this->assertEmpty($data['items']);
        $this->assertEquals(0, $data['count']);
    }

    public function testAddItemToWishlistSuccessfully()
    {
        Auth::$user = null;

        $product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'price' => 99.99,
            'is_active' => true,
            'site_id' => $this->siteId
        ]);

        $response = $this->postForSite('/api/wishlist/add', [
            'product_id' => $product->id
        ]);

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals('Product added to wishlist', $data['message']);
        $this->assertEquals(1, $data['count']);
    }

    public function testAddItemFailsWithoutProductId()
    {
        $response = $this->postForSite('/api/wishlist/add', []);

        $this->assertResponseStatus(400, $response);
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['success']);
        $this->assertEquals('Product ID required', $data['message']);
    }

    public function testAddItemFailsWhenAlreadyInWishlist()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'price' => 99.99,
            'site_id' => $this->siteId
        ]);

        // Add first time
        $this->postForSite('/api/wishlist/add', ['product_id' => $product->id]);

        // Try to add again
        $response = $this->postForSite('/api/wishlist/add', ['product_id' => $product->id]);
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['success']);
        $this->assertEquals('Product already in wishlist', $data['message']);
    }

    public function testRemoveItemFromWishlist()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'price' => 99.99,
            'site_id' => $this->siteId
        ]);

        // Add item
        $this->postForSite('/api/wishlist/add', ['product_id' => $product->id]);

        // Remove item
        $response = $this->deleteForSite("/api/wishlist/remove/{$product->id}");

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals(0, $data['count']);
    }

    public function testRemoveItemFailsWhenNotInWishlist()
    {
        $response = $this->deleteForSite('/api/wishlist/remove/999');

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['success']);
        $this->assertEquals('Item not found in wishlist', $data['message']);
    }

    public function testGetWishlistItems()
    {
        $product1 = Product::create([
            'name' => 'Product 1',
            'slug' => 'product-1',
            'price' => 50.00,
            'sale_price' => 40.00,
            'site_id' => $this->siteId
        ]);

        $product2 = Product::create([
            'name' => 'Product 2',
            'slug' => 'product-2',
            'price' => 30.00,
            'site_id' => $this->siteId
        ]);

        $this->postForSite('/api/wishlist/add', ['product_id' => $product1->id]);
        $this->postForSite('/api/wishlist/add', ['product_id' => $product2->id]);

        $response = $this->getForSite('/api/wishlist');
        $data = json_decode($response->getContent(), true);

        $this->assertCount(2, $data['items']);
        $this->assertEquals(2, $data['count']);
        $this->assertEquals('Product 1', $data['items'][0]['product_name']);
    }
}