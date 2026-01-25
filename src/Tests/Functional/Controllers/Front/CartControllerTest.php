<?php

namespace App\Tests\Functional\Controllers\Front;

use App\Framework\Authorization\Auth;
use App\Models\Product;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class CartControllerTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanupDatabase();
        $this->ensureSiteExists();
    }

    public function testIndexReturnsEmptyCart()
    {
        $response = $this->getForSite('/api/cart');

        $this->assertResponseOk($response);

        $data = json_decode($response->getContent(), true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('count', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertEmpty($data['items']);
        $this->assertEquals(0, $data['count']);
        $this->assertEquals(0, $data['total']);
    }

    public function testAddItemToCartSuccessfully()
    {
        Auth::$user = null;

        $product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'price' => 99.99,
            'is_active' => true,
            'stock_quantity' => 10,
            'site_id' => $this->siteId
        ]);

        $response = $this->postForSite('/api/cart/add', [
            'product_id' => $product->id,
            'quantity' => 2
        ]);

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('success', $data);
        $this->assertTrue($data['success']);
        $this->assertEquals('Product added to cart', $data['message']);
        $this->assertEquals(2, $data['count']);
    }

    public function testAddItemFailsWithoutProductId()
    {
        $response = $this->postForSite('/api/cart/add', []);

        $this->assertResponseStatus(400, $response);
        $data = json_decode($response->getContent(), true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('message', $data);
        $this->assertFalse($data['success']);
        $this->assertEquals('Product ID required', $data['message']);
    }

    public function testAddItemFailsForInactiveProduct()
    {
        $product = Product::create([
            'name' => 'Inactive Product',
            'slug' => 'inactive-product',
            'price' => 99.99,
            'is_active' => false,
            'site_id' => $this->siteId
        ]);

        $response = $this->postForSite('/api/cart/add', [
            'product_id' => $product->id
        ]);

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['success']);
    }

    public function testUpdateCartItemQuantity()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'price' => 99.99,
            'is_active' => true,
            'stock_quantity' => 10,
            'site_id' => $this->siteId
        ]);

        // Add item first
        $addResponse = $this->postForSite('/api/cart/add', [
            'product_id' => $product->id,
            'quantity' => 1
        ]);

        // Verify item was added
        $this->assertResponseOk($addResponse);

        // Get cart to find item ID
        $cartResponse = $this->getForSite('/api/cart');
        $cartData = json_decode($cartResponse->getContent(), true);

        // Debug if items is empty
        if (empty($cartData['items'])) {
            $this->fail('Cart items array is empty. Cart data: ' . json_encode($cartData));
        }

        $itemId = $cartData['items'][0]['id'];

        // Update quantity
        $response = $this->putForSite("/api/cart/update/{$itemId}", [
            'quantity' => 5
        ]);

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals(5, $data['count']);
    }

    public function testUpdateQuantityFailsWithoutQuantity()
    {
        $response = $this->putForSite('/api/cart/update/1', []);

        $this->assertResponseStatus(400, $response);
        $data = json_decode($response->getContent(), true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('message', $data);
        $this->assertFalse($data['success']);
        $this->assertEquals('Quantity required', $data['message']);
    }

    public function testRemoveItemFromCart()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'price' => 99.99,
            'is_active' => true,
            'site_id' => $this->siteId,
            'stock_quantity' => 100
        ]);

        // Add item
        $addResponse = $this->postForSite('/api/cart/add', [
            'product_id' => $product->id
        ]);
        $this->assertResponseOk($addResponse);

        // Get cart to find item ID
        $cartResponse = $this->getForSite('/api/cart');
        $cartData = json_decode($cartResponse->getContent(), true);

        if (empty($cartData['items'])) {
            $this->fail('Cart items array is empty');
        }

        $itemId = $cartData['items'][0]['id'];

        // Remove item
        $response = $this->deleteForSite("/api/cart/remove/{$itemId}");

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals(0, $data['count']);
    }

    public function testClearCart()
    {
        $product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'price' => 99.99,
            'is_active' => true,
            'site_id' => $this->siteId
        ]);

        // Add multiple items
        $this->postForSite('/api/cart/add', ['product_id' => $product->id]);
        $this->postForSite('/api/cart/add', ['product_id' => $product->id]);

        // Clear cart
        $response = $this->postForSite('/api/cart/clear');

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals(0, $data['count']);
        $this->assertEquals(0, $data['total']);
    }

    public function testCartCalculatesTotalCorrectly()
    {
        $product1 = Product::create([
            'name' => 'Product 1',
            'slug' => 'product-1',
            'price' => 50.00,
            'is_active' => true,
            'site_id' => $this->siteId,
            'stock_quantity' => 100
        ]);

        $product2 = Product::create([
            'name' => 'Product 2',
            'slug' => 'product-2',
            'price' => 30.00,
            'is_active' => true,
            'site_id' => $this->siteId,
            'stock_quantity' => 100
        ]);

        $this->postForSite('/api/cart/add', [
            'product_id' => $product1->id,
            'quantity' => 2
        ]);

        $this->postForSite('/api/cart/add', [
            'product_id' => $product2->id,
            'quantity' => 1
        ]);

        $response = $this->getForSite('/api/cart');
        $data = json_decode($response->getContent(), true);

        // Verify individual subtotals are correct
        $this->assertEquals(100.00, $data['items'][0]['subtotal']);
        $this->assertEquals(30.00, $data['items'][1]['subtotal']);

        $this->assertEquals(130.00, $data['total']);
        $this->assertEquals(3, $data['count']);
    }
}