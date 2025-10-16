<?php

namespace App\Tests\Unit\Models;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class CartItemModelTest extends FunctionalTestCase
{
    protected CartItem $cartItem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cartItem = new CartItem([
            'session_id' => 'test_session_123',
            'user_id' => 1,
            'product_id' => 5,
            'quantity' => 3,
            'price' => 29.99,
            'options' => json_encode(['color' => 'red', 'size' => 'M']),
            'site_id' => 1,
            'subtotal' => 89.97
        ]);
    }

    public function testCartItemCanBeInstantiated()
    {
        $this->assertInstanceOf(CartItem::class, $this->cartItem);
    }

    public function testCartItemHasCorrectTableName()
    {
        $this->assertEquals('cart_items', $this->cartItem->getTable());
    }

    public function testUserRelationReturnsCorrectType()
    {
        $relation = $this->cartItem->user();
        $this->assertInstanceOf(User::class, $relation);
    }

    public function testGetSubtotalCalculatesCorrectly()
    {
        $this->cartItem->price = 25.00;
        $this->cartItem->quantity = 4;

        $subtotal = $this->cartItem->getSubtotal();
        $this->assertEquals(100.00, $subtotal);
    }

    public function testGetSubtotalHandlesZeroQuantity()
    {
        $this->cartItem->price = 25.00;
        $this->cartItem->quantity = 0;

        $subtotal = $this->cartItem->getSubtotal();
        $this->assertEquals(0.00, $subtotal);
    }

    public function testGetSubtotalHandlesZeroPrice()
    {
        $this->cartItem->price = 0.00;
        $this->cartItem->quantity = 5;

        $subtotal = $this->cartItem->getSubtotal();
        $this->assertEquals(0.00, $subtotal);
    }

    // Attribute Getter/Setter Tests
    public function testSetAndGetSessionId()
    {
        $this->cartItem->session_id = 'new_session_456';
        $this->assertEquals('new_session_456', $this->cartItem->session_id);
    }

    public function testSetAndGetUserId()
    {
        $this->cartItem->user_id = 10;
        $this->assertEquals(10, $this->cartItem->user_id);
    }

    public function testSetAndGetProductId()
    {
        $this->cartItem->product_id = 15;
        $this->assertEquals(15, $this->cartItem->product_id);
    }

    public function testSetAndGetQuantity()
    {
        $this->cartItem->quantity = 7;
        $this->assertEquals(7, $this->cartItem->quantity);
    }

    public function testSetAndGetPrice()
    {
        $this->cartItem->price = 49.99;
        $this->assertEquals(49.99, $this->cartItem->price);
    }

    public function testSetAndGetOptions()
    {
        $options = ['color' => 'blue', 'size' => 'L'];
        $this->cartItem->options = $options;
        $this->assertEquals($options, $this->cartItem->options);
    }

    public function testSetAndGetSiteId()
    {
        $this->cartItem->site_id = 2;
        $this->assertEquals(2, $this->cartItem->site_id);
    }

    public function testSetAndGetSubtotal()
    {
        $this->cartItem->subtotal = 199.95;
        $this->assertEquals(199.95, $this->cartItem->subtotal);
    }

    public function testOptionsAreCastedToJson()
    {
        $options = ['color' => 'green', 'size' => 'XL'];
        $this->cartItem->options = $options;

        // Verify it can be retrieved as array
        $retrieved = $this->cartItem->options;
        $this->assertIsArray($retrieved);
        $this->assertEquals('green', $retrieved['color']);
        $this->assertEquals('XL', $retrieved['size']);
    }

    public function testQuantityIsCastedToInteger()
    {
        $this->cartItem->quantity = '10';
        $this->assertIsInt($this->cartItem->quantity);
        $this->assertEquals(10, $this->cartItem->quantity);
    }

    public function testPriceIsCastedToFloat()
    {
        $this->cartItem->price = '29.99';
        $this->assertIsFloat($this->cartItem->price);
        $this->assertEquals(29.99, $this->cartItem->price);
    }

    public function testToArrayIncludesAllAttributes()
    {
        $array = $this->cartItem->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('session_id', $array);
        $this->assertArrayHasKey('user_id', $array);
        $this->assertArrayHasKey('product_id', $array);
        $this->assertArrayHasKey('quantity', $array);
        $this->assertArrayHasKey('price', $array);
        $this->assertArrayHasKey('options', $array);
        $this->assertArrayHasKey('site_id', $array);
    }

    public function testCreateCartItem()
    {
        $user = User::create(['name' => 'John', 'email' => '<EMAIL>', 'password' => '<PASSWORD>', 'site_id' => 1]);;
        $product = Product::create(['name' => 'Test Product', 'price' => 99.99, 'site_id' => 1]);;

        $cartItem = CartItem::create([
            'session_id' => 'session_abc123',
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'price' => 99.99,
            'site_id' => 1,
            'subtotal' => 499.95,
        ]);

        $this->assertInstanceOf(CartItem::class, $cartItem);
        $this->assertEquals('session_abc123', $cartItem->session_id);
        $this->assertEquals($product->id, $cartItem->product_id);
        $this->assertEquals(5, $cartItem->quantity);
    }

    public function testFillMethodPopulatesAttributes()
    {
        $cartItem = new CartItem();
        $cartItem->fill([
            'session_id' => 'new_session',
            'quantity' => 3,
            'price' => 29.99
        ]);

        $this->assertEquals('new_session', $cartItem->session_id);
        $this->assertEquals(3, $cartItem->quantity);
        $this->assertEquals(29.99, $cartItem->price);
    }

    public function testOptionsCanStoreComplexData()
    {
        $complexOptions = [
            'color' => 'red',
            'size' => 'L',
            'customization' => [
                'text' => 'Hello',
                'font' => 'Arial'
            ]
        ];

        $this->cartItem->options = $complexOptions;
        $retrieved = $this->cartItem->options;

        $this->assertEquals('red', $retrieved['color']);
        $this->assertIsArray($retrieved['customization']);
        $this->assertEquals('Hello', $retrieved['customization']['text']);
    }
}