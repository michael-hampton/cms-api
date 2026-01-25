<?php

namespace App\Tests\Unit\Services\Front;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\SubscriptionPlan;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Shop\CartRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Services\Shop\CartService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class CartServiceTest extends FunctionalTestCase
{
    private $cartRepository;
    private $productRepository;
    private CartService $service;
    private $subscriptionPlanRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cartRepository = Mockery::mock(CartRepository::class);
        $this->productRepository = Mockery::mock(ProductRepository::class);
        $this->subscriptionPlanRepository = Mockery::mock(SubscriptionPlanRepository::class);

        $this->service = new CartService(
            $this->cartRepository,
            $this->productRepository,
            $this->subscriptionPlanRepository,
        );

        $_SESSION['cart_session_id'] = 'test_session_123';
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetItemsReturnsEmptyArrayWhenNoItems()
    {
        $this->cartRepository->shouldReceive('findBySessionOrUser')
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
            'image_url' => '/images/test.jpg'
        ]);

        $cartItem = new CartItem([
            'id' => 1,
            'product_id' => 1,
            'quantity' => 2,
            'price' => 99.99,
            'subtotal' => 199.98,  // ADD THIS
            'options' => []
        ]);

        $cartItem->setRelation('product', $product);

        $this->cartRepository->shouldReceive('findBySessionOrUser')
            ->once()
            ->andReturn(collect([$cartItem]));

        $result = $this->service->getItems();

        $this->assertCount(1, $result);
        $this->assertEquals('Test Product', $result[0]['product_name']);
        $this->assertEquals(199.98, $result[0]['subtotal']);
    }

    public function testAddItemSuccessfully()
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->shouldReceive('getAttribute')
            ->with('id')
            ->andReturn(1);
        $product->shouldReceive('getAttribute')
            ->with('is_active')
            ->andReturn(true);
        $product->shouldReceive('getAttribute')
            ->with('stock_quantity')
            ->andReturn(10);
        $product->shouldReceive('getAttribute')
            ->with('price')
            ->andReturn(99.99);
        $product->shouldReceive('getAttribute')
            ->with('sale_price')
            ->andReturn(null);
        $product->shouldReceive('getAttribute')
            ->with('site_id')
            ->andReturn(1);
        $product->is_active = true;
        $product->stock_quantity = 10;

        $this->productRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($product);

        $this->cartRepository->shouldReceive('findItemByProduct')
            ->once()
            ->andReturn(null);

        $this->cartRepository->shouldReceive('create')
            ->once()
            ->andReturn(new CartItem());

        $result = $this->service->addItem(1, 2);

        $this->assertTrue($result['success']);
        $this->assertEquals('Product added to cart', $result['message']);
    }


    public function testAddItemFailsWhenProductNotFound()
    {
        $this->productRepository->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $result = $this->service->addItem(999);

        $this->assertFalse($result['success']);
        $this->assertEquals('Product not found or inactive', $result['message']);
    }

    public function testAddItemFailsWhenProductInactive()
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->shouldReceive('getAttribute')
            ->with('is_active')
            ->andReturn(false);
        $product->is_active = false;

        $this->productRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($product);

        $result = $this->service->addItem(1);

        $this->assertFalse($result['success']);
        $this->assertEquals('Product not found or inactive', $result['message']);
    }

    public function testAddItemFailsWhenInsufficientStock()
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->shouldReceive('getAttribute')
            ->with('is_active')
            ->andReturn(true);
        $product->shouldReceive('getAttribute')
            ->with('stock_quantity')
            ->andReturn(2);
        $product->is_active = true;
        $product->stock_quantity = 2;

        $this->productRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($product);

        $result = $this->service->addItem(1, 5);

        $this->assertFalse($result['success']);
        $this->assertEquals('Insufficient stock', $result['message']);
    }

    public function testAddItemUpdatesQuantityWhenItemExists()
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->shouldReceive('getAttribute')->with('is_active')->andReturn(true);
        $product->shouldReceive('getAttribute')->with('stock_quantity')->andReturn(10);
        $product->shouldReceive('getAttribute')->with('price')->andReturn(50.00);
        $product->shouldReceive('getAttribute')->with('sale_price')->andReturn(null);
        $product->is_active = true;
        $product->stock_quantity = 10;

        $existingItem = Mockery::mock(CartItem::class)->makePartial();
        $existingItem->shouldReceive('getAttribute')->with('quantity')->andReturn(2);
        $existingItem->shouldReceive('getAttribute')->with('price')->andReturn(50.00);  // ADD THIS
        $existingItem->quantity = 2;
        $existingItem->price = 50.00;  // ADD THIS
        $existingItem->shouldReceive('update')
            ->once()
            ->with(Mockery::on(function($data) {
                return $data['quantity'] === 4
                    && $data['subtotal'] === 200.00;  // ADD THIS VALIDATION (50 * 4)
            }));

        $this->productRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($product);

        $this->cartRepository->shouldReceive('findItemByProduct')
            ->once()
            ->andReturn($existingItem);

        $result = $this->service->addItem(1, 2);

        $this->assertTrue($result['success']);
    }

    public function testUpdateQuantitySuccessfully()
    {
        $product = new Product([
            'id' => 1,
            'stock_quantity' => 10
        ]);

        $cartItem = new CartItem([
            'id' => 1,
            'product_id' => 1,
            'price' => 99.99  // ADD THIS
        ]);
        $cartItem->setRelation('product', $product);

        $this->cartRepository->shouldReceive('findById')
            ->with(1, null, 'test_session_123')
            ->once()
            ->andReturn($cartItem);

        $this->cartRepository->shouldReceive('update')
            ->with(1, Mockery::on(function($data) {
                return $data['quantity'] === 5
                    && $data['subtotal'] === 499.95;  // ADD THIS VALIDATION (99.99 * 5)
            }))
            ->once()
            ->andReturn($cartItem);

        $result = $this->service->updateQuantity(1, 5);

        $this->assertTrue($result['success']);
        $this->assertEquals('Cart updated', $result['message']);
    }

    public function testUpdateQuantityFailsWhenItemNotFound()
    {
        $this->cartRepository->shouldReceive('findById')
            ->once()
            ->andReturn(null);

        $result = $this->service->updateQuantity(999, 5);

        $this->assertFalse($result['success']);
        $this->assertEquals('Cart item not found', $result['message']);
    }

    public function testRemoveItemSuccessfully()
    {
        $cartItem = Mockery::mock(CartItem::class);

        $this->cartRepository->shouldReceive('findById')
            ->with(1, null, 'test_session_123')
            ->once()
            ->andReturn($cartItem);

        $this->cartRepository->shouldReceive('delete')
            ->with(1)
            ->once()
            ->andReturn(true);

        $result = $this->service->removeItem(1);

        $this->assertTrue($result['success']);
        $this->assertEquals('Item removed from cart', $result['message']);
    }

    public function testRemoveItemFailsWhenNotFound()
    {
        $this->cartRepository->shouldReceive('findById')
            ->once()
            ->andReturn(null);

        $result = $this->service->removeItem(999);

        $this->assertFalse($result['success']);
        $this->assertEquals('Cart item not found', $result['message']);
    }

    public function testClearCart()
    {
        $this->cartRepository->shouldReceive('deleteBySessionOrUser')
            ->once()
            ->andReturn(true);

        $this->service->clear();

        $this->assertTrue(true); // If no exception, test passes
    }

    public function testGetTotalCalculatesCorrectly()
    {
        $product1 = new Product(['id' => 1, 'name' => 'Product 1', 'slug' => 'product-1']);
        $product2 = new Product(['id' => 2, 'name' => 'Product 2', 'slug' => 'product-2']);

        $item1 = new CartItem([
            'id' => 1,
            'quantity' => 2,
            'price' => 50.00,
            'subtotal' => 100.00  // ADD THIS
        ]);
        $item1->product = $product1;

        $item2 = new CartItem([
            'id' => 2,
            'quantity' => 1,
            'price' => 30.00,
            'subtotal' => 30.00  // ADD THIS
        ]);
        $item2->product = $product2;

        $this->cartRepository->shouldReceive('findBySessionOrUser')
            ->once()
            ->andReturn(collect([$item1, $item2]));

        $total = $this->service->getTotal();

        $this->assertEquals(130.00, $total);
    }

    public function testGetCountReturnsCorrectCount()
    {
        $this->cartRepository->shouldReceive('getCountBySessionOrUser')
            ->with(null, 'test_session_123')
            ->once()
            ->andReturn(5);

        $count = $this->service->getCount();

        $this->assertEquals(5, $count);
    }

    public function testGetTotalReturnsZeroWhenEmpty()
    {
        $this->cartRepository->shouldReceive('findBySessionOrUser')
            ->once()
            ->andReturn(collect([]));

        $total = $this->service->getTotal();

        $this->assertEquals(0, $total);
    }

    public function testAddOneTimeSubscriptionSuccess(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $plan->shouldReceive('getAttribute')->with('isOneTime')->andReturn(true);
        $plan->shouldReceive('getAttribute')->with('price')->andReturn(99.99);
        $plan->shouldReceive('getAttribute')->with('site_id')->andReturn(1);
        $plan->shouldReceive('isOneTime')->andReturn(true);
        $plan->shouldReceive('getDeliveryOptions')->andReturn(['digital', 'print']);

        $this->productRepository->shouldReceive('find')->never();

        $this->subscriptionPlanRepository->shouldReceive('find')->with(1)->andReturn($plan);

        $this->cartRepository->shouldReceive('findBySubscriptionPlan')
            ->once()
            ->andReturn(null);

        $this->cartRepository->shouldReceive('create')
            ->once()
            ->andReturn(new CartItem());

        $result = $this->service->addOneTimeSubscription(1, 'digital');

        $this->assertTrue($result['success']);
        $this->assertEquals('Subscription added to cart', $result['message']);
    }

    public function testAddOneTimeSubscriptionFailsWithInvalidDeliveryType(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->shouldReceive('isOneTime')->andReturn(true);
        $plan->shouldReceive('getDeliveryOptions')->andReturn(['digital']);

        $this->subscriptionPlanRepository->shouldReceive('find')->with(1)->andReturn($plan);

        $result = $this->service->addOneTimeSubscription(1, 'print');

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid delivery type', $result['message']);
    }

    public function testAddOneTimeSubscriptionFailsWhenAlreadyInCart(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->shouldReceive('isOneTime')->andReturn(true);
        $plan->shouldReceive('getDeliveryOptions')->andReturn(['digital']);

        $this->subscriptionPlanRepository->shouldReceive('find')->with(1)->andReturn($plan);

        $existingItem = Mockery::mock(CartItem::class);
        $this->cartRepository->shouldReceive('findBySubscriptionPlan')
            ->once()
            ->andReturn($existingItem);

        $result = $this->service->addOneTimeSubscription(1, 'digital');

        $this->assertFalse($result['success']);
        $this->assertEquals('Subscription plan already in cart', $result['message']);
    }

}