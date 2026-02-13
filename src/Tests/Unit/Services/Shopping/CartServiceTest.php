<?php

namespace App\Tests\Unit\Services\Shopping;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductOfferBundle;
use App\Models\ProductOfferBundleItem;
use App\Models\SubscriptionPlan;
use App\Repositories\Offers\ProductOfferBundleRepository;
use App\Repositories\Offers\ProductOfferRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Shopping\CartRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Services\Shopping\CartService;
use App\Services\Vouchers\VoucherService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery;

class CartServiceTest extends FunctionalTestCase
{
    use CreatesTestData;

    private $cartRepository;
    private $productRepository;
    private CartService $service;
    private $subscriptionPlanRepository;
    private VoucherService $voucherService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cartRepository = Mockery::mock(CartRepository::class);
        $this->productRepository = Mockery::mock(ProductRepository::class);
        $this->subscriptionPlanRepository = Mockery::mock(SubscriptionPlanRepository::class);
        $this->offerRepository = Mockery::mock(ProductOfferRepository::class);
        $this->bundleRepository = Mockery::mock(ProductOfferBundleRepository::class);
        $this->voucherService = Mockery::mock(VoucherService::class);

        $this->service = new CartService(
            $this->cartRepository,
            $this->productRepository,
            $this->subscriptionPlanRepository,
            $this->offerRepository,
            $this->bundleRepository,
            $this->voucherService
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

        $this->cartRepository->shouldReceive('findBySessionOrUser')
            ->once()
            ->andReturn(collect([]));

        $this->productRepository->shouldReceive('find')
            ->with(1, ['availableMerchants'])
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
            ->with(999, ['availableMerchants'])
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
            ->with(1, ['availableMerchants'])
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

        $this->cartRepository->shouldReceive('findBySessionOrUser')
            ->once()
            ->andReturn(collect([]));

        $this->cartRepository->shouldReceive('findItemByProduct')
            ->once()
            ->andReturn($product);

        $this->productRepository->shouldReceive('find')
            ->with(1, ['availableMerchants'])
            ->once()
            ->andReturn($product);

        $result = $this->service->addItem(1, 5);

        $this->assertFalse($result['success']);
        $this->assertEquals('Cannot add more items. Stock limit reached.', $result['message']);
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

        $this->cartRepository->shouldReceive('findBySessionOrUser')
            ->once()
            ->andReturn(collect([$existingItem]));

        $this->productRepository->shouldReceive('find')
            ->with(1, ['availableMerchants'])
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

        $this->subscriptionPlanRepository->shouldReceive('find')->with(1, ['pricingTiers'])->andReturn($plan);

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

        $this->subscriptionPlanRepository->shouldReceive('find')->with(1, ['pricingTiers'])->andReturn($plan);

        $result = $this->service->addOneTimeSubscription(1, 'print');

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid delivery type', $result['message']);
    }

    public function testAddOneTimeSubscriptionFailsWhenAlreadyInCart(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->shouldReceive('isOneTime')->andReturn(true);
        $plan->shouldReceive('getDeliveryOptions')->andReturn(['digital']);

        $this->subscriptionPlanRepository->shouldReceive('find')->with(1, ['pricingTiers'])->andReturn($plan);

        $existingItem = Mockery::mock(CartItem::class);
        $this->cartRepository->shouldReceive('findBySubscriptionPlan')
            ->once()
            ->andReturn($existingItem);

        $result = $this->service->addOneTimeSubscription(1, 'digital');

        $this->assertFalse($result['success']);
        $this->assertEquals('Subscription plan already in cart', $result['message']);
    }

    public function testAddItemFailsWhenProductHasOfferInCart()
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $product->shouldReceive('getAttribute')->with('is_active')->andReturn(true);
        $product->is_active = true;

        $existingCartItem = Mockery::mock(CartItem::class)->makePartial();
        $existingCartItem->shouldReceive('getAttribute')->with('product_id')->andReturn(1);
        $existingCartItem->shouldReceive('isOffer')->andReturn(true);
        $existingCartItem->shouldReceive('isBundle')->andReturn(false);

        $this->productRepository->shouldReceive('find')
            ->with(1, ['availableMerchants'])
            ->once()
            ->andReturn($product);

        $this->cartRepository->shouldReceive('findBySessionOrUser')
            ->once()
            ->andReturn(collect([$existingCartItem]));

        $result = $this->service->addItem(1);

        $this->assertFalse($result['success']);
        $this->assertEquals('Product already in cart with a promotion', $result['message']);
    }

    public function testAddItemFailsWhenProductHasBundleInCart()
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $product->shouldReceive('getAttribute')->with('is_active')->andReturn(true);
        $product->is_active = true;

        $existingCartItem = Mockery::mock(CartItem::class)->makePartial();
        $existingCartItem->shouldReceive('getAttribute')->with('product_id')->andReturn(1);
        $existingCartItem->shouldReceive('isOffer')->andReturn(false);
        $existingCartItem->shouldReceive('isBundle')->andReturn(true);

        $this->productRepository->shouldReceive('find')
            ->with(1, ['availableMerchants'])
            ->once()
            ->andReturn($product);

        $this->cartRepository->shouldReceive('findBySessionOrUser')
            ->once()
            ->andReturn(collect([$existingCartItem]));

        $result = $this->service->addItem(1);

        $this->assertFalse($result['success']);
        $this->assertEquals('Product already in cart with a promotion', $result['message']);
    }

    public function testGetItemsIncludesOfferBadge()
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
            'subtotal' => 199.98,
            'options' => ['type' => 'offer', 'offer_id' => 5]
        ]);

        $cartItem->setRelation('product', $product);

        $this->cartRepository->shouldReceive('findBySessionOrUser')
            ->once()
            ->andReturn(collect([$cartItem]));

        $result = $this->service->getItems();

        $this->assertCount(1, $result);
        $this->assertEquals('offer', $result[0]['item_type']);
        $this->assertEquals(5, $result[0]['offer_id']);
        $this->assertEquals('Limited-time offer', $result[0]['badge']);
    }

    public function testGetItemsIncludesBundleBadge()
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
            'subtotal' => 199.98,
            'options' => ['type' => 'bundle', 'bundle_id' => 10]
        ]);

        $cartItem->setRelation('product', $product);

        $this->cartRepository->shouldReceive('findBySessionOrUser')
            ->once()
            ->andReturn(collect([$cartItem]));

        $result = $this->service->getItems();

        $this->assertCount(1, $result);
        $this->assertEquals('bundle', $result[0]['item_type']);
        $this->assertEquals(10, $result[0]['bundle_id']);
        $this->assertEquals('Bundle deal', $result[0]['badge']);
    }

    public function testGetItemsIncludesMerchantId()
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
            'subtotal' => 199.98,
            'options' => ['type' => 'offer', 'offer_id' => 5, 'merchant_id' => 3]
        ]);

        $cartItem->setRelation('product', $product);

        $this->cartRepository->shouldReceive('findBySessionOrUser')
            ->once()
            ->andReturn(collect([$cartItem]));

        $result = $this->service->getItems();

        $this->assertCount(1, $result);
        $this->assertEquals(3, $result[0]['merchant_id']);
    }

    public function testAddOfferToCartSuccess()
    {
        $product = $this->createProduct();
        $offer = $this->createProductOffer($product->id, [
            'sale_price' => 79.99
        ]);

        $this->offerRepository->shouldReceive('find')
            ->with($offer->id)
            ->once()
            ->andReturn($offer);

        $this->cartRepository->shouldReceive('findItemByProduct')
            ->once()
            ->andReturn(null);

        $this->cartRepository->shouldReceive('create')
            ->once()
            ->andReturn(new CartItem());

        $result = $this->service->addOfferToCart($offer->id);

        $this->assertTrue($result['success']);
        $this->assertEquals('Offer added to cart', $result['message']);
    }

    public function testAddOfferToCartFailsWithInactiveOffer()
    {
        $product = $this->createProduct();
        $offer = $this->createProductOffer($product->id, [
            'is_active' => false
        ]);

        $this->offerRepository->shouldReceive('find')
            ->with($offer->id)
            ->once()
            ->andReturn($offer);

        $result = $this->service->addOfferToCart($offer->id);

        $this->assertFalse($result['success']);
        $this->assertEquals('Offer not available', $result['message']);
    }

    public function testAddOfferToCartFailsWhenProductAlreadyInCart()
    {
        $product = $this->createProduct();
        $offer = $this->createProductOffer($product->id);

        $existingCartItem = Mockery::mock(CartItem::class);

        $this->offerRepository->shouldReceive('find')
            ->with($offer->id)
            ->once()
            ->andReturn($offer);

        $this->cartRepository->shouldReceive('findItemByProduct')
            ->once()
            ->andReturn($existingCartItem);

        $result = $this->service->addOfferToCart($offer->id);

        $this->assertFalse($result['success']);
        $this->assertEquals('Product already in cart', $result['message']);
    }

    public function testAddBundleToCartSuccess()
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
        ]);

        ProductOfferBundleItem::create([
            'bundle_id' => $bundle->id,
            'product_offer_id' => $offer1->id,
            'quantity' => 1,
        ]);

        ProductOfferBundleItem::create([
            'bundle_id' => $bundle->id,
            'product_offer_id' => $offer2->id,
            'quantity' => 1,
        ]);

        $bundle->load(['items.productOffer.product']);

        $this->bundleRepository->shouldReceive('find')
            ->with($bundle->id)
            ->once()
            ->andReturn($bundle);

        $this->cartRepository->shouldReceive('create')
            ->twice()
            ->andReturn(new CartItem());

        $result = $this->service->addBundleToCart($bundle->id);

        $this->assertTrue($result['success']);
        $this->assertEquals('Bundle added to cart', $result['message']);
        $this->assertCount(2, $result['cart_items']);
    }

    public function testAddBundleToCartFailsWithInactiveBundle()
    {
        $bundle = ProductOfferBundle::create([
            'name' => 'Test Bundle',
            'slug' => 'test-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'discount_percentage' => 25,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => false,
        ]);

        $this->bundleRepository->shouldReceive('find')
            ->with($bundle->id)
            ->once()
            ->andReturn($bundle);

        $result = $this->service->addBundleToCart($bundle->id);

        $this->assertFalse($result['success']);
        $this->assertEquals('Bundle not available', $result['message']);
    }

}