<?php

namespace App\Tests\Unit\Models;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class CartItemModelTest extends FunctionalTestCase
{
    use CreatesTestData;
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
        $member = $this->createMember();
        $product = Product::create(['name' => 'Test Product', 'price' => 99.99, 'site_id' => 1]);;

        $cartItem = CartItem::create([
            'session_id' => 'session_abc123',
            'user_id' => $member->id,
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

    public function test_subscription_plan_relationship(): void
    {
        $plan = $this->createSubscriptionPlan([
            'name' => 'Premium Magazine',
            'price' => 50.00
        ]);

        $cartItem = CartItem::create([
            'session_id' => 'test-session',
            'user_id' => null,
            'product_id' => null,
            'subscription_plan_id' => $plan->id,
            'quantity' => 1,
            'price' => 50.00,
            'site_id' => $this->siteId,
            'subtotal' => 50.00,
        ]);

        $loadedPlan = $cartItem->subscriptionPlan;

        $this->assertNotNull($loadedPlan);
        $this->assertEquals($plan->id, $loadedPlan->id);
        $this->assertEquals('Premium Magazine', $loadedPlan->name);
    }

    public function test_subscription_plan_relationship_returns_null_when_not_subscription(): void
    {
        $product = $this->createProduct();

        $cartItem = CartItem::create([
            'session_id' => 'test-session',
            'user_id' => null,
            'product_id' => $product->id,
            'subscription_plan_id' => null,
            'quantity' => 2,
            'price' => 25.00,
            'site_id' => $this->siteId,
            'subtotal' => 25.00,
        ]);

        $loadedPlan = $cartItem->subscriptionPlan;

        $this->assertNull($loadedPlan);
    }

    public function test_is_subscription_returns_true_when_subscription_plan_id_set(): void
    {
        $plan = $this->createSubscriptionPlan();

        $cartItem = CartItem::create([
            'session_id' => 'test-session',
            'user_id' => null,
            'product_id' => null,
            'subscription_plan_id' => $plan->id,
            'quantity' => 1,
            'price' => 50.00,
            'site_id' => $this->siteId,
            'subtotal' => 50.00,
        ]);

        $this->assertTrue($cartItem->isSubscription());
    }

    public function test_is_subscription_returns_false_when_subscription_plan_id_null(): void
    {
        $product = $this->createProduct();

        $cartItem = CartItem::create([
            'session_id' => 'test-session',
            'user_id' => null,
            'product_id' => $product->id,
            'subscription_plan_id' => null,
            'quantity' => 2,
            'price' => 25.00,
            'site_id' => $this->siteId,
            'subtotal' => 25.00,
        ]);

        $this->assertFalse($cartItem->isSubscription());
    }

    public function test_get_subtotal_calculates_correctly(): void
    {
        $product = $this->createProduct(['price' => 46.50]);

        $cartItem = CartItem::create([
            'session_id' => 'test-session',
            'user_id' => null,
            'product_id' => $product->id,
            'quantity' => 3,
            'price' => 15.50,
            'site_id' => $this->siteId,
            'subtotal' => 15.50,
        ]);

        $subtotal = $cartItem->getSubtotal();

        $this->assertEquals(46.50, $subtotal);
    }

    public function test_product_relationship(): void
    {
        $product = $this->createProduct(['name' => 'Test Product']);

        $cartItem = CartItem::create([
            'session_id' => 'test-session',
            'user_id' => null,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 20.00,
            'site_id' => $this->siteId,
            'subtotal' => 20.00,
        ]);

        $loadedProduct = $cartItem->product;

        $this->assertNotNull($loadedProduct);
        $this->assertEquals($product->id, $loadedProduct->id);
        $this->assertEquals('Test Product', $loadedProduct->name);
    }

    public function test_options_casts_to_json(): void
    {
        $options = [
            'size' => 'Large',
            'color' => 'Blue',
            'delivery_type' => 'digital'
        ];

        $product = $this->createProduct($options);

        $cartItem = CartItem::create([
            'session_id' => 'test-session',
            'user_id' => null,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 30.00,
            'site_id' => $this->siteId,
            'options' => $options,
            'subtotal' => 30.00,
        ]);

        $this->assertIsArray($cartItem->options);
        $this->assertEquals('Large', $cartItem->options['size']);
        $this->assertEquals('Blue', $cartItem->options['color']);
        $this->assertEquals('digital', $cartItem->options['delivery_type']);
    }

    public function testGetItemTypeReturnsProduct(): void
    {
        $product = $this->createProduct();

        $cartItem = CartItem::create([
            'session_id' => 'test-session',
            'user_id' => null,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 20.00,
            'site_id' => $this->siteId,
            'subtotal' => 20.00,
            'options' => []
        ]);

        $this->assertEquals('product', $cartItem->getItemType());
    }

    public function testGetItemTypeReturnsOffer(): void
    {
        $product = $this->createProduct();
        $offer = $this->createProductOffer($product->id);

        $cartItem = CartItem::create([
            'session_id' => 'test-session',
            'user_id' => null,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 20.00,
            'site_id' => $this->siteId,
            'subtotal' => 20.00,
            'options' => ['type' => 'offer', 'offer_id' => $offer->id]
        ]);

        $this->assertEquals('offer', $cartItem->getItemType());
    }

    public function testGetItemTypeReturnsBundle(): void
    {
        $product = $this->createProduct();

        $cartItem = CartItem::create([
            'session_id' => 'test-session',
            'user_id' => null,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 20.00,
            'site_id' => $this->siteId,
            'subtotal' => 20.00,
            'options' => ['type' => 'bundle', 'bundle_id' => 1]
        ]);

        $this->assertEquals('bundle', $cartItem->getItemType());
    }

    public function testIsOfferReturnsTrueForOfferItems(): void
    {
        $product = $this->createProduct();
        $offer = $this->createProductOffer($product->id);

        $cartItem = CartItem::create([
            'session_id' => 'test-session',
            'user_id' => null,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 20.00,
            'site_id' => $this->siteId,
            'subtotal' => 20.00,
            'options' => ['type' => 'offer', 'offer_id' => $offer->id]
        ]);

        $this->assertTrue($cartItem->isOffer());
    }

    public function testIsOfferReturnsFalseForNonOfferItems(): void
    {
        $product = $this->createProduct();

        $cartItem = CartItem::create([
            'session_id' => 'test-session',
            'user_id' => null,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 20.00,
            'site_id' => $this->siteId,
            'subtotal' => 20.00,
            'options' => []
        ]);

        $this->assertFalse($cartItem->isOffer());
    }

    public function testIsBundleReturnsTrueForBundleItems(): void
    {
        $product = $this->createProduct();

        $cartItem = CartItem::create([
            'session_id' => 'test-session',
            'user_id' => null,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 20.00,
            'site_id' => $this->siteId,
            'subtotal' => 20.00,
            'options' => ['type' => 'bundle', 'bundle_id' => 1]
        ]);

        $this->assertTrue($cartItem->isBundle());
    }

    public function testIsBundleReturnsFalseForNonBundleItems(): void
    {
        $product = $this->createProduct();

        $cartItem = CartItem::create([
            'session_id' => 'test-session',
            'user_id' => null,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 20.00,
            'site_id' => $this->siteId,
            'subtotal' => 20.00,
            'options' => []
        ]);

        $this->assertFalse($cartItem->isBundle());
    }

    public function testGetBundleIdReturnsNullForNonBundleItems(): void
    {
        $product = $this->createProduct();

        $cartItem = CartItem::create([
            'session_id' => 'test-session',
            'user_id' => null,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 20.00,
            'site_id' => $this->siteId,
            'subtotal' => 20.00,
            'options' => []
        ]);

        $this->assertNull($cartItem->getBundleId());
    }

    public function testGetBundleIdReturnsBundleIdForBundleItems(): void
    {
        $product = $this->createProduct();

        $cartItem = CartItem::create([
            'session_id' => 'test-session',
            'user_id' => null,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 20.00,
            'site_id' => $this->siteId,
            'subtotal' => 20.00,
            'options' => ['type' => 'bundle', 'bundle_id' => 123]
        ]);

        $this->assertEquals(123, $cartItem->getBundleId());
    }

    public function testGetOfferIdReturnsNullForNonOfferItems(): void
    {
        $product = $this->createProduct();

        $cartItem = CartItem::create([
            'session_id' => 'test-session',
            'user_id' => null,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 20.00,
            'site_id' => $this->siteId,
            'subtotal' => 20.00,
            'options' => []
        ]);

        $this->assertNull($cartItem->getOfferId());
    }

    public function testGetOfferIdReturnsOfferIdForOfferItems(): void
    {
        $product = $this->createProduct();
        $offer = $this->createProductOffer($product->id);

        $cartItem = CartItem::create([
            'session_id' => 'test-session',
            'user_id' => null,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 20.00,
            'site_id' => $this->siteId,
            'subtotal' => 20.00,
            'options' => ['type' => 'offer', 'offer_id' => $offer->id]
        ]);

        $this->assertEquals($offer->id, $cartItem->getOfferId());
    }

    public function testGetMerchantIdReturnsNullWhenNotSet(): void
    {
        $product = $this->createProduct();

        $cartItem = CartItem::create([
            'session_id' => 'test-session',
            'user_id' => null,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 20.00,
            'site_id' => $this->siteId,
            'subtotal' => 20.00,
            'options' => []
        ]);

        $this->assertNull($cartItem->getMerchantId());
    }

    public function testGetMerchantIdReturnsMerchantIdWhenSet(): void
    {
        $product = $this->createProduct();
        $merchant = $this->createMerchant();

        $cartItem = CartItem::create([
            'session_id' => 'test-session',
            'user_id' => null,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 20.00,
            'site_id' => $this->siteId,
            'subtotal' => 20.00,
            'options' => ['merchant_id' => $merchant->id]
        ]);

        $this->assertEquals($merchant->id, $cartItem->getMerchantId());
    }
}