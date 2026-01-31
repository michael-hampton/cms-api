<?php

namespace App\Tests\Functional\Controllers\Shopping;

use App\Framework\Authorization\Auth;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Session\Session;
use App\Models\Product;
use App\Models\Wishlist;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class WishlistControllerTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanupDatabase();
        $this->ensureSiteExists();

        // Clear session state between tests
        Session::flush();
        MemberAuth::$member = null;

        // Clean wishlist table
        Wishlist::query()->delete();
    }

    protected function tearDown(): void
    {
        Session::flush();
        Auth::$user = null;
        parent::tearDown();
    }

    public function testIndexReturnsEmptyWishlist()
    {
        $response = $this->getForSite('/api/wishlist');

        $this->assertResponseOk($response);
        $this->assertJsonResponse($response);

        $data = json_decode($response->getContent(), true);

        $this->assertIsArray($data['items']);
        $this->assertEmpty($data['items']);
        $this->assertEquals(0, $data['count']);
    }

    public function testAddItemToWishlistSuccessfully()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product-' . uniqid(),
            'price' => 99.99,
            'is_active' => true,
            'site_id' => $this->siteId
        ]);

        $response = $this->postForSite('/api/wishlist/add', [
            'product_id' => $product->id
        ]);

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertIsArray($data);
        $this->assertTrue($data['success']);
        $this->assertEquals('Product added to wishlist', $data['message']);
        $this->assertGreaterThanOrEqual(1, $data['count']);
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
            'slug' => 'test-product-' . uniqid(),
            'price' => 99.99,
            'site_id' => $this->siteId,
            'is_active' => true
        ]);

        // Add first time
        $firstResponse = $this->postForSite('/api/wishlist/add', ['product_id' => $product->id]);
        $this->assertResponseOk($firstResponse);

        // Try to add again
        $response = $this->postForSite('/api/wishlist/add', ['product_id' => $product->id]);
        $this->assertResponseOk($response);

        $data = json_decode($response->getContent(), true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('success', $data);
        $this->assertFalse($data['success']);
        $this->assertEquals('Product already in wishlist', $data['message']);
    }

    public function testRemoveItemFromWishlist()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product-' . uniqid(),
            'price' => 99.99,
            'site_id' => $this->siteId,
            'is_active' => true
        ]);

        // Add item
        $addResponse = $this->postForSite('/api/wishlist/add', ['product_id' => $product->id]);
        $this->assertResponseOk($addResponse);

        // Remove item
        $response = $this->deleteForSite("/api/wishlist/remove/{$product->id}");

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertIsArray($data);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('count', $data);
    }

    public function testRemoveItemFailsWhenNotInWishlist()
    {
        $response = $this->deleteForSite('/api/wishlist/remove/99999');

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertIsArray($data);
        $this->assertFalse($data['success']);
        $this->assertEquals('Item not found in wishlist', $data['message']);
    }

    public function testGetWishlistItems()
    {
        $product1 = Product::create([
            'name' => 'Product 1',
            'slug' => 'product-1-' . uniqid(),
            'price' => 50.00,
            'sale_price' => 40.00,
            'site_id' => $this->siteId,
            'is_active' => true
        ]);

        $product2 = Product::create([
            'name' => 'Product 2',
            'slug' => 'product-2-' . uniqid(),
            'price' => 30.00,
            'site_id' => $this->siteId,
            'is_active' => true
        ]);

        $this->postForSite('/api/wishlist/add', ['product_id' => $product1->id]);
        $this->postForSite('/api/wishlist/add', ['product_id' => $product2->id]);

        $response = $this->getForSite('/api/wishlist');
        $data = json_decode($response->getContent(), true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('items', $data);
        $this->assertCount(2, $data['items']);
        $this->assertEquals(2, $data['count']);

        // Check product names are present
        $productNames = array_column($data['items'], 'product_name');
        $this->assertContains('Product 1', $productNames);
        $this->assertContains('Product 2', $productNames);
    }
}