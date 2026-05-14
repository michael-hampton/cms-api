<?php

namespace App\Tests\Unit\Services\Shopping;

use App\Enums\CartItemType;
use App\Enums\Subscriptions\SubscriptionType;
use App\Framework\Database\Database;
use App\Models\CartItem;
use App\Models\IssueDelivery;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\ProductOfferBundle;
use App\Models\ProductOfferBundleItem;
use App\Models\ProductVariant;
use App\Models\SubscriptionBundle;
use App\Models\SubscriptionBundleItem;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPricing;
use App\Repositories\Offers\ProductOfferBundleRepository;
use App\Repositories\Offers\ProductOfferRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Product\ProductVariantRepository;
use App\Repositories\Shopping\CartRepository;
use App\Repositories\Subscriptions\SubscriptionBundleRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Services\Billing\Preorder\Contracts\AvailabilityPolicyInterface;
use App\Services\Shipping\ShippingService;
use App\Services\Shopping\CartService;
use App\Services\Shopping\Factories\CartItemFactory;
use App\Services\Shopping\Resolvers\CartPriceResolver;
use App\Services\Shopping\Resolvers\CartStockResolver;
use App\Services\Subscriptions\Calculators\SubscriptionBundlePriceAllocator;
use App\Services\Vouchers\VoucherService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery;
use ReflectionMethod;
use RuntimeException;

class CartServiceTest extends FunctionalTestCase
{
    use CreatesTestData;

    private $cartRepository;
    private $productRepository;
    private CartService $service;
    private $subscriptionPlanRepository;
    private $offerRepository;
    private $bundleRepository;
    private VoucherService $voucherService;
    private ProductVariantRepository $productVariantRepository;
    private Database $databaseMock;
    private CartStockResolver $stockResolver;
    private CartPriceResolver $priceResolver;
    private CartItemFactory $itemFactory;
    private ShippingService $shippingService;
    private SubscriptionBundleRepository $subscriptionBundleRepository;
    private SubscriptionBundlePriceAllocator $subscriptionBundlePriceAllocator;

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
            'subtotal' => 199.98,
            'options' => []
        ]);

        $cartItem->setRelation('product', $product);

        // No subscription_plan_id on this item so repository should not be called
        $this->subscriptionPlanRepository->shouldNotReceive('find');

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
        $product->id = 1;
        $product->price = 99.99;
        $product->sale_price = null;
        $product->site_id = 1;
        $product->is_active = true;
        $product->stock_quantity = 10;
        $product->shouldReceive('getAttribute')->with('is_active')->andReturn(true);
        $product->shouldReceive('getAttribute')->with('stock_quantity')->andReturn(10);
        $product->shouldReceive('getAttribute')->with('price')->andReturn(99.99);
        $product->shouldReceive('getAttribute')->with('sale_price')->andReturn(null);

        $product->shouldReceive('getAttribute')->with('availableMerchants')->andReturn(collect([]));
        $product->availableMerchants = collect([]);

        $this->cartRepository->shouldReceive('findBySessionOrUser')
            ->once()
            ->andReturn(collect([]));

        $this->productRepository->shouldReceive('find')
            ->with(1, ['availableMerchants', 'variants'])
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
            ->with(999, ['availableMerchants', 'variants'])
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
            ->with(1, ['availableMerchants', 'variants'])
            ->once()
            ->andReturn($product);

        $result = $this->service->addItem(1);

        $this->assertFalse($result['success']);
        $this->assertEquals('Product not found or inactive', $result['message']);
    }

    public function testAddItemFailsWhenInsufficientStock()
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->shouldReceive('getAttribute')->with('is_active')->andReturn(true);
        $product->shouldReceive('getAttribute')->with('stock_quantity')->andReturn(2);
        $product->shouldReceive('getAttribute')->with('variants')->andReturn(collect([]));
        $product->is_active = true;
        $product->stock_quantity = 2;

        $this->productRepository->shouldReceive('find')
            ->with(1, ['availableMerchants', 'variants'])
            ->once()
            ->andReturn($product);

        $this->cartRepository->shouldReceive('findBySessionOrUser')->andReturn(collect());

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
            ->with(Mockery::on(function ($data) {
                return $data['quantity'] === 4
                    && $data['subtotal'] === 200.00;  // ADD THIS VALIDATION (50 * 4)
            }));

        $this->cartRepository->shouldReceive('findBySessionOrUser')
            ->once()
            ->andReturn(collect([$existingItem]));

        $this->productRepository->shouldReceive('find')
            ->with(1, ['availableMerchants', 'variants'])
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
        $product = Mockery::mock(Product::class)->makePartial();
        $product->shouldReceive('getAttribute')->with('stock_quantity')->andReturn(10);
        $product->stock_quantity = 10;

        $cartItem = Mockery::mock(CartItem::class)->makePartial();
        $cartItem->shouldReceive('getAttribute')->with('price')->andReturn(50.00);
        $cartItem->shouldReceive('getAttribute')->with('product')->andReturn($product);
        $cartItem->shouldReceive('getAttribute')->with('variant_id')->andReturn(null);
        $cartItem->shouldReceive('getAttribute')->with('variant')->andReturn(null);
        $cartItem->price = 50.00;
        $cartItem->product = $product;
        $cartItem->variant_id = null;

        $this->cartRepository->shouldReceive('findById')
            ->once()
            ->andReturn($cartItem);

        $this->cartRepository->shouldReceive('update')
            ->once()
            ->with(1, Mockery::on(function ($data) {
                return $data['quantity'] === 3 && $data['subtotal'] === 150.00;
            }));

        $result = $this->service->updateQuantity(1, 3);

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
        $cartItem = Mockery::mock(CartItem::class)->makePartial();

        $this->cartRepository->shouldReceive('findById')
            ->once()
            ->andReturn($cartItem);

        $this->cartRepository->shouldReceive('findBySessionOrUser')
            ->once()
            ->andReturn(collect([$cartItem]));

        $this->cartRepository->shouldReceive('delete')
            ->once()
            ->with(1);

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
        $item1 = new CartItem(['subtotal' => 100.00]);
        $item2 = new CartItem(['subtotal' => 199.97]);

        $this->cartRepository->shouldReceive('findBySessionOrUser')
            ->once()
            ->andReturn(collect([$item1, $item2]));

        $result = $this->service->getTotal();

        $this->assertEquals(299.97, $result);
    }

    public function testGetCountReturnsCorrectCount()
    {
        $this->cartRepository->shouldReceive('getCountBySessionOrUser')
            ->with(null, Mockery::any())
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
        $plan->is_active = true;
        $plan->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $plan->shouldReceive('getAttribute')->with('isOneTime')->andReturn(true);
        $plan->shouldReceive('getAttribute')->with('price')->andReturn(99.99);
        $plan->shouldReceive('getAttribute')->with('site_id')->andReturn(1);
        $plan->shouldReceive('isOneTime')->andReturn(true);
        $plan->shouldReceive('getDeliveryOptions')->andReturn([SubscriptionType::DIGITAL->value, SubscriptionType::PRINTED->value]);

        $this->productRepository->shouldReceive('find')->never();

        $this->subscriptionPlanRepository->shouldReceive('find')->with(1, ['pricingTiers'])->andReturn($plan);

        $this->cartRepository->shouldReceive('findBySubscriptionPlan')
            ->once()
            ->andReturn(null);

        $this->cartRepository->shouldReceive('create')
            ->once()
            ->andReturn(new CartItem());

        $result = $this->service->addSubscriptionToCart(1, SubscriptionType::DIGITAL->value);

        $this->assertTrue($result['success']);
        $this->assertEquals('Subscription added to cart', $result['message']);
    }

    public function testAddOneTimeSubscriptionFailsWithInvalidDeliveryType(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->is_active = true;
        $plan->shouldReceive('isOneTime')->andReturn(true);
        $plan->shouldReceive('getDeliveryOptions')->andReturn(['SubscriptionType::DIGITAL->value']);

        $this->subscriptionPlanRepository->shouldReceive('find')->with(1, ['pricingTiers'])->andReturn($plan);

        $result = $this->service->addSubscriptionToCart(1, 'print');

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid delivery type', $result['message']);
    }

    public function testAddOneTimeSubscriptionFailsWhenAlreadyInCart(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->is_active = true;
        $plan->shouldReceive('isOneTime')->andReturn(true);
        $plan->shouldReceive('getDeliveryOptions')->andReturn([SubscriptionType::DIGITAL->value]);

        $this->subscriptionPlanRepository->shouldReceive('find')->with(1, ['pricingTiers'])->andReturn($plan);

        $existingItem = Mockery::mock(CartItem::class);
        $this->cartRepository->shouldReceive('findBySubscriptionPlan')
            ->once()
            ->andReturn($existingItem);

        $result = $this->service->addOneTimeSubscription(1, SubscriptionType::DIGITAL->value);

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
            ->with(1, ['availableMerchants', 'variants'])
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
            ->with(1, ['availableMerchants', 'variants'])
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

        $this->cartRepository->shouldReceive('findBySessionOrUser')
            ->once()
            ->andReturn(collect());

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

        $existingCartItem = Mockery::mock(CartItem::class)->makePartial();
        $existingCartItem->product_id = $product->id;

        $this->offerRepository->shouldReceive('find')
            ->with($offer->id)
            ->once()
            ->andReturn($offer);

        $this->cartRepository->shouldReceive('findBySessionOrUser')
            ->once()
            ->andReturn(collect([$existingCartItem]));

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
            'site_id' => $this->siteId
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

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

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
            'site_id' => $this->siteId
        ]);

        $this->bundleRepository->shouldReceive('find')
            ->with($bundle->id)
            ->once()
            ->andReturn($bundle);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $result = $this->service->addBundleToCart($bundle->id);

        $this->assertFalse($result['success']);
        $this->assertEquals('Bundle not available', $result['message']);
    }

    public function test_add_item_with_variant(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->is_active = true;
        $product->stock_quantity = 100;
        $product->price = 99.99;
        $product->sale_price = 0;
        $product->site_id = 1;

        $product->shouldReceive('getAttribute')->with('is_active')->andReturn(true);
        $product->shouldReceive('getAttribute')->with('stock_quantity')->andReturn(100);
        $product->shouldReceive('getAttribute')->with('price')->andReturn(99.99);
        $product->shouldReceive('getAttribute')->with('sale_price')->andReturn(0);
        $product->shouldReceive('getAttribute')->with('availableMerchants')->andReturn(collect([]));
        $product->availableMerchants = collect([]);

        $variant = Mockery::mock(ProductVariant::class)->makePartial();
        $variant->id = 1;
        $variant->price = 79.99;
        $variant->stock_quantity = 50;
        $variant->shouldReceive('getAttribute')->with('price')->andReturn(79.99);
        $variant->shouldReceive('getAttribute')->with('sale_price')->andReturn(null);
        $variant->shouldReceive('getAttribute')->with('stock_quantity')->andReturn(50);

        $this->productRepository->shouldReceive('getVariantById')
            ->once()
            ->andReturn($variant);

        $variantCollection = collect([$variant]);
        $product->shouldReceive('variants')->andReturn($variantCollection);

        $this->productRepository->shouldReceive('find')
            ->with($product->id, ['availableMerchants', 'variants'])
            ->once()
            ->andReturn($product);

        $this->cartRepository->shouldReceive('findBySessionOrUser')
            ->once()
            ->andReturn(collect([]));

        $this->cartRepository->shouldReceive('findItemByProduct')
            ->once()
            ->andReturn(null);

        $this->cartRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) use ($product, $variant) {
                return $data['product_id'] == $product->id
                    && $data['variant_id'] == $variant->id
                    && $data['price'] == $variant->price;
            }))
            ->andReturn(new CartItem());

        $result = $this->service->addItem($product->id, 1, [], $variant->id);

        $this->assertTrue($result['success']);
    }

    public function test_add_item_fails_with_invalid_variant(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->is_active = true;


        $this->productRepository->shouldReceive('getVariantById')
            ->once()
            ->with(999)
            ->andReturn(null);

        $this->productRepository->shouldReceive('find')
            ->with(1, ['availableMerchants', 'variants'])
            ->once()
            ->andReturn($product);

        $result = $this->service->addItem(1, 1, [], 999);

        $this->assertFalse($result['success']);
        $this->assertEquals('Variant not found', $result['message']);
    }

    public function test_get_items_includes_variant_information(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->is_active = true;
        $variant = Mockery::mock(ProductVariant::class)->makePartial();
        $variant->id = 1;
        $variant->price = 79.99;
        $variant->sku = 'Test';

        $cartItem = new CartItem([
            'id' => 1,
            'product_id' => 1,
            'variant_id' => 1,
            'quantity' => 2,
            'price' => 99.99,
            'subtotal' => 199.98,
            'options' => []
        ]);
        $cartItem->variant = $variant;

        $this->productRepository->shouldReceive('getVariantById')
            ->once()
            ->with(1)
            ->andReturn($variant);

        $this->cartRepository->shouldReceive('findBySessionOrUser')
            ->once()
            ->andReturn(collect([$cartItem]));

        $result = $this->service->getItems();

        $this->assertCount(1, $result);
        $this->assertEquals(1, $result[0]['variant_id']);
        //$this->assertEquals(['size' => 'L', 'color' => 'Red'], $result[0]['variant_options']);
        $this->assertEquals('Test', $result[0]['sku']);
    }

    public function testUpdateQuantityBelowOneRemovesItem(): void
    {
        $cartItem = Mockery::mock(CartItem::class)->makePartial();

        $this->cartRepository->shouldReceive('findById')
            ->once()
            ->andReturn($cartItem);

        $this->cartRepository->shouldReceive('findBySessionOrUser')
            ->once()
            ->andReturn(collect([$cartItem]));

        $this->cartRepository->shouldReceive('delete')
            ->once();

        $result = $this->service->updateQuantity(1, 0);

        $this->assertTrue($result['success']);
        $this->assertEquals('Item removed from cart', $result['message']);
    }

    public function testUpdateQuantityFailsOnInsufficientStock(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->stock_quantity = 2;

        $cartItem = Mockery::mock(CartItem::class)->makePartial();
        $cartItem->product = $product;
        $cartItem->variant_id = null;
        $cartItem->shouldReceive('getAttribute')->with('product')->andReturn($product);
        $cartItem->shouldReceive('getAttribute')->with('variant_id')->andReturn(null);
        $cartItem->shouldReceive('getAttribute')->with('variant')->andReturn(null);

        $this->cartRepository->shouldReceive('findById')
            ->once()
            ->andReturn($cartItem);

        $result = $this->service->updateQuantity(1, 10);

        $this->assertFalse($result['success']);
        $this->assertEquals('Cannot add more items. Stock limit reached.', $result['message']);
    }

    public function testAddSubscriptionToCartFailsWhenPlanNotFound(): void
    {
        $this->subscriptionPlanRepository->shouldReceive('find')
            ->with(999, ['pricingTiers'])
            ->once()
            ->andReturn(null);

        $result = $this->service->addSubscriptionToCart(999, 'digital');

        $this->assertFalse($result['success']);
        $this->assertEquals('Subscription plan not found or inactive', $result['message']);
    }

    public function testAddSubscriptionToCartForRegularSubscriptionWithInactiveProduct(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->site_id = $this->siteId;
        $plan->is_active = false;
        $plan->shouldReceive('isOneTime')->andReturn(false);
        $plan->shouldReceive('getDeliveryOptions')->andReturn([SubscriptionType::DIGITAL->value]);
        $plan->shouldReceive('getAttribute')->with('price')->andReturn(29.99);

        $this->subscriptionPlanRepository->shouldReceive('find')
            ->with(1, ['pricingTiers'])
            ->andReturn($plan);

        $result = $this->service->addSubscriptionToCart(1, SubscriptionType::DIGITAL->value);

        $this->assertFalse($result['success']);
        $this->assertEquals('Subscription plan not found or inactive', $result['message']);
    }

    public function testAddSubscriptionToCartForRegularSubscriptionSuccess(): void
    {

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;
        $plan->shouldReceive('isOneTime')->andReturn(false);
        $plan->shouldReceive('getDeliveryOptions')->andReturn([SubscriptionType::DIGITAL->value]);
        $plan->shouldReceive('getAttribute')->with('price')->andReturn(29.99);
        $plan->pricingTiers = collect([]);
        $plan->is_active = true;
        $plan->site_id = $this->siteId;

        $this->subscriptionPlanRepository->shouldReceive('find')
            ->with(1, ['pricingTiers'])
            ->andReturn($plan);

        $this->cartRepository->shouldReceive('findBySubscriptionPlan')
            ->once()
            ->andReturn(null);

        $this->cartRepository->shouldReceive('create')
            ->once()
            ->andReturn(new CartItem());

        $result = $this->service->addSubscriptionToCart(1, SubscriptionType::DIGITAL->value);

        $this->assertTrue($result['success']);
        $this->assertEquals('Subscription added to cart', $result['message']);
    }

    public function testGetPriceForSubscriptionUsesDigitalSalePriceWhenCheaper(): void
    {
        $pricingTier = Mockery::mock(SubscriptionPlanPricing::class)->makePartial();
        $pricingTier->digital_price = 20.00;
        $pricingTier->digital_sale_price = 15.00; // cheaper

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;
        $plan->is_active = true;
        $plan->shouldReceive('isOneTime')->andReturn(true);
        $plan->shouldReceive('getDeliveryOptions')->andReturn([SubscriptionType::DIGITAL->value]);
        $plan->pricingTiers = collect([$pricingTier]);
        $pricingTier->id = 99;
        $plan->shouldReceive('getAttribute')->with('price')->andReturn(20.00);
        $plan->site_id = 1;

        $this->subscriptionPlanRepository->shouldReceive('find')
            ->with(1, ['pricingTiers'])
            ->andReturn($plan);

        $this->cartRepository->shouldReceive('findBySubscriptionPlan')->andReturn(null);

        $this->cartRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['price'] === 15.00;
            }))
            ->andReturn(new CartItem());

        $result = $this->service->addSubscriptionToCart(1, SubscriptionType::DIGITAL->value, [
            'pricing_tier_id' => 99
        ]);

        $this->assertTrue($result['success']);
    }

    public function testHasOnlyDigitalItemsReturnsTrueForDigitalSubscription(): void
    {
        $cartItem = Mockery::mock(CartItem::class)->makePartial();
        $cartItem->subscription_plan_id = 1;
        $cartItem->options = json_encode(['delivery_type' => SubscriptionType::DIGITAL->value]);

        $this->cartRepository->shouldReceive('findBySessionOrUser')
            ->once()
            ->andReturn(collect([$cartItem]));

        $this->assertTrue($this->service->hasOnlyDigitalItems());
    }

    public function testHasOnlyDigitalItemsReturnsFalseForPrintSubscription(): void
    {
        $cartItem = Mockery::mock(CartItem::class)->makePartial();
        $cartItem->subscription_plan_id = 1;
        $cartItem->options = json_encode(['delivery_type' => SubscriptionType::PRINTED->value]);

        $this->cartRepository->shouldReceive('findBySessionOrUser')
            ->once()
            ->andReturn(collect([$cartItem]));

        $this->assertFalse($this->service->hasOnlyDigitalItems());
    }

    public function testHasOnlyDigitalItemsReturnsTrueForEmptyCart(): void
    {
        $this->cartRepository->shouldReceive('findBySessionOrUser')
            ->once()
            ->andReturn(collect([]));

        $this->assertTrue($this->service->hasOnlyDigitalItems());
    }

    public function testHasOnlyDigitalItemsReturnsFalseForPhysicalProduct(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->is_digital = false;

        $cartItem = Mockery::mock(CartItem::class)->makePartial();
        $cartItem->subscription_plan_id = null;
        $cartItem->variant_id = null;
        $cartItem->product = $product;
        $cartItem->shouldReceive('getAttribute')->with('product')->andReturn($product);
        $cartItem->shouldReceive('getAttribute')->with('subscription_plan_id')->andReturn(null);
        $cartItem->shouldReceive('getAttribute')->with('variant_id')->andReturn(null);

        $this->cartRepository->shouldReceive('findBySessionOrUser')
            ->once()
            ->andReturn(collect([$cartItem]));

        $this->assertFalse($this->service->hasOnlyDigitalItems());
    }

    public function testRequiresShippingReturnsTrueWhenPhysicalItemsPresent(): void
    {
        $cartItem = Mockery::mock(CartItem::class)->makePartial();
        $cartItem->subscription_plan_id = 1;
        $cartItem->options = json_encode(['delivery_type' => SubscriptionType::PRINTED->value]);

        $this->cartRepository->shouldReceive('findBySessionOrUser')
            ->once()
            ->andReturn(collect([$cartItem]));

        $this->assertTrue($this->service->requiresShipping());
    }

    public function testUpdateStartDateSuccessfully(): void
    {
        $cartItem = Mockery::mock(CartItem::class)->makePartial();
        $cartItem->options = ['delivery_type' => 'digital'];

        $this->cartRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($cartItem);

        $this->cartRepository->shouldReceive('update')
            ->once()
            ->with(1, Mockery::on(function ($data) {
                return isset($data['options']['start_date'])
                    && $data['options']['start_date'] === '2025-06-01';
            }));

        $result = $this->service->updateStartDate(1, '2025-06-01');

        $this->assertTrue($result['success']);
        $this->assertEquals('Start date updated', $result['message']);
    }

    public function testUpdateStartDateFailsWhenItemNotFound(): void
    {
        $this->cartRepository->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $result = $this->service->updateStartDate(999, '2025-06-01');

        $this->assertFalse($result['success']);
        $this->assertEquals('Cart item not found', $result['message']);
    }

    public function testAddOfferToCartFailsWhenProductInactive(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->is_active = false;

        $offer = Mockery::mock(ProductOffer::class)->makePartial();
        $offer->is_active = true;
        $offer->product = $product;

        $this->offerRepository->shouldReceive('find')
            ->once()
            ->andReturn($offer);

        $result = $this->service->addOfferToCart(1);

        $this->assertFalse($result['success']);
        $this->assertEquals('Product not available', $result['message']);
    }

    public function testAddBundleToCartFailsWhenBundleNotFound(): void
    {
        $this->bundleRepository->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $result = $this->service->addBundleToCart(999);

        $this->assertFalse($result['success']);
        $this->assertEquals('Bundle not available', $result['message']);
    }

    public function test_adds_each_bundle_plan_as_separate_cart_item(): void
    {
        [$bundle, $planA, $planB, $productA, $productB] = $this->makeBundle();

        $this->subscriptionBundleRepository->shouldReceive('find')
            ->with($bundle->id)
            ->once()
            ->andReturn($bundle);

        $this->cartRepository->shouldReceive('findBySubscriptionPlan')
            ->twice()
            ->andReturn(null);

        $this->subscriptionBundlePriceAllocator->shouldReceive('allocate')
            ->with($bundle)
            ->once()
            ->andReturn([
                $planA->id => 30.00,
                $planB->id => 20.00,
            ]);

        $this->cartRepository->shouldReceive('create')
            ->twice()
            ->andReturn(new CartItem());

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $result = $this->service->addSubscriptionBundleToCart($bundle->id);

        $this->assertTrue($result['success']);
        $this->assertEquals('Subscription bundle added to cart', $result['message']);
        $this->assertCount(2, $result['cart_items']);
        $this->assertEquals($bundle->id, $result['bundle_id']);
    }

    /**
     * Build a mock subscription bundle with two one-time plans.
     *
     * @return array [bundle, planA, planB, productA, productB]
     */
    private function makeBundle(): array
    {
        $productA = Mockery::mock(Product::class)->makePartial();
        $productA->id = 10;
        $productA->is_active = true;
        $productA->site_id = 1;
        $productA->name = 'Plan A Product';

        $productB = Mockery::mock(Product::class)->makePartial();
        $productB->id = 11;
        $productB->is_active = true;
        $productB->site_id = 1;
        $productB->name = 'Plan B Product';

        $planA = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $planA->id = 101;
        $planA->name = 'Plan A';
        $planA->price = 40.00;
        $planA->site_id = 1;
        $planA->shouldReceive('isOneTime')->andReturn(false);
        $planA->shouldReceive('getDeliveryOptions')->andReturn(['digital', 'print']);
        $planA->product = $productA;

        $planB = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $planB->id = 102;
        $planB->name = 'Plan B';
        $planB->price = 30.00;
        $planB->site_id = 1;
        $planB->shouldReceive('isOneTime')->andReturn(false);
        $planB->shouldReceive('getDeliveryOptions')->andReturn(['digital', 'print']);
        $planB->product = $productB;

        $itemA = Mockery::mock(SubscriptionBundleItem::class)->makePartial();
        $itemA->subscription_plan_id = $planA->id;
        $itemA->delivery_type = 'digital';
        $itemA->quantity = 1;
        $itemA->subscriptionPlan = $planA;

        $itemB = Mockery::mock(SubscriptionBundleItem::class)->makePartial();
        $itemB->subscription_plan_id = $planB->id;
        $itemB->delivery_type = 'print';
        $itemB->quantity = 1;
        $itemB->subscriptionPlan = $planB;

        $bundle = Mockery::mock(SubscriptionBundle::class)->makePartial();
        $bundle->id = 7;
        $bundle->bundle_price = 50.00;
        $bundle->total_price = 70.00;
        $bundle->shouldReceive('isCurrentlyActive')->andReturn(true);
        $bundle->items = collect([$itemA, $itemB]);

        return [$bundle, $planA, $planB, $productA, $productB];
    }

    public function test_cart_item_carries_bundle_id_and_allocated_price(): void
    {
        [$bundle, $planA, $planB, $productA, $productB] = $this->makeBundle();

        $this->subscriptionBundleRepository->shouldReceive('find')
            ->once()
            ->andReturn($bundle);

        $this->cartRepository->shouldReceive('findBySubscriptionPlan')
            ->twice()
            ->andReturn(null);

        $this->subscriptionBundlePriceAllocator->shouldReceive('allocate')
            ->once()
            ->andReturn([
                $planA->id => 36.00,
                $planB->id => 24.00,
            ]);

        // Assert first item gets planA's allocated price and bundle_id in options
        $this->cartRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) use ($bundle, $planA) {
                $options = is_string($data['options'])
                    ? json_decode($data['options'], true)
                    : $data['options'];

                return $data['price'] === 36.00
                    && $data['subscription_plan_id'] === $planA->id
                    && $options['bundle_id'] === $bundle->id
                    && $options['type'] === CartItemType::SUBSCRIPTION_BUNDLE->value;
            }))
            ->andReturn(new CartItem());

        // Second item
        $this->cartRepository->shouldReceive('create')
            ->once()
            ->andReturn(new CartItem());

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $result = $this->service->addSubscriptionBundleToCart($bundle->id);

        $this->assertTrue($result['success']);
    }

    public function test_uses_database_transaction(): void
    {
        [$bundle] = $this->makeBundle();

        $this->subscriptionBundleRepository->shouldReceive('find')
            ->once()
            ->andReturn($bundle);

        $this->cartRepository->shouldReceive('findBySubscriptionPlan')
            ->twice()
            ->andReturn(null);

        $this->subscriptionBundlePriceAllocator->shouldReceive('allocate')
            ->once()
            ->andReturn([$bundle->items->first()->subscription_plan_id => 30.00,
                $bundle->items->last()->subscription_plan_id => 20.00]);

        $this->cartRepository->shouldReceive('create')
            ->twice()
            ->andReturn(new CartItem());

        // Assert transaction is used
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                $this->assertIsCallable($callback);
                return $callback();
            });

        $this->service->addSubscriptionBundleToCart($bundle->id);
    }

    public function test_fails_when_bundle_not_found(): void
    {
        $this->subscriptionBundleRepository->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $result = $this->service->addSubscriptionBundleToCart(999);

        $this->assertFalse($result['success']);
        $this->assertEquals('Subscription bundle not available', $result['message']);
    }

    // -----------------------------------------------------------------------
    // Guard: bundle not found / inactive
    // -----------------------------------------------------------------------

    public function test_fails_when_bundle_is_inactive(): void
    {
        $bundle = Mockery::mock(SubscriptionBundle::class)->makePartial();
        $bundle->id = 1;
        $bundle->shouldReceive('isCurrentlyActive')->andReturn(false);

        $this->subscriptionBundleRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($bundle);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $result = $this->service->addSubscriptionBundleToCart(1);

        $this->assertFalse($result['success']);
        $this->assertEquals('Subscription bundle not available', $result['message']);
    }

    public function test_fails_when_bundle_has_no_items(): void
    {
        $bundle = Mockery::mock(SubscriptionBundle::class)->makePartial();
        $bundle->id = 1;
        $bundle->shouldReceive('isCurrentlyActive')->andReturn(true);
        $bundle->items = collect([]);

        $this->subscriptionBundleRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($bundle);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $result = $this->service->addSubscriptionBundleToCart(1);

        $this->assertFalse($result['success']);
        $this->assertEquals('Subscription bundle has no plans', $result['message']);
    }

    public function test_fails_when_a_bundle_plan_is_already_in_cart(): void
    {
        [$bundle, $planA] = $this->makeBundle();

        $this->subscriptionBundleRepository->shouldReceive('find')
            ->once()
            ->andReturn($bundle);

        // planA is already in the cart
        $this->cartRepository->shouldReceive('findBySubscriptionPlan')
            ->with($planA->id, Mockery::any(), Mockery::any())
            ->once()
            ->andReturn(new CartItem());

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $result = $this->service->addSubscriptionBundleToCart($bundle->id);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already in your cart', $result['message']);
    }

    // -----------------------------------------------------------------------
    // Guard: duplicate plan already in cart
    // -----------------------------------------------------------------------

    public function test_transaction_rolls_back_when_plan_not_found(): void
    {
        [$bundle, $planA, $planB] = $this->makeBundle();

        // Make planA unavailable
        $bundle->items->first()->subscriptionPlan = null;

        $this->subscriptionBundleRepository->shouldReceive('find')
            ->once()
            ->andReturn($bundle);

        $this->cartRepository->shouldReceive('findBySubscriptionPlan')
            ->twice()
            ->andReturn(null);

        $this->subscriptionBundlePriceAllocator->shouldReceive('allocate')
            ->once()
            ->andReturn([$planA->id => 30.00, $planB->id => 20.00]);

        $exceptionThrown = false;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($cb) use (&$exceptionThrown) {
                try {
                    return $cb();
                } catch (RuntimeException $e) {
                    $exceptionThrown = true;
                    throw $e;
                }
            });

        $this->expectException(RuntimeException::class);

        $this->service->addSubscriptionBundleToCart($bundle->id);

        $this->assertTrue($exceptionThrown, 'Exception should have triggered rollback');
    }

    // -----------------------------------------------------------------------
    // Rollback on runtime error
    // -----------------------------------------------------------------------

    public function test_bundle_items_are_visible_to_getItems_as_subscription_items(): void
    {
        $product = new Product(['id' => 1, 'name' => 'Mag', 'slug' => 'mag', 'image' => null]);

        $cartItem = new CartItem([
            'id' => 10,
            'product_id' => 1,
            'subscription_plan_id' => 42,
            'quantity' => 1,
            'price' => 30.00,
            'subtotal' => 30.00,
            'options' => [
                'type' => CartItemType::SUBSCRIPTION_BUNDLE->value,
                'bundle_id' => 7,
                'delivery_type' => 'digital',
            ],
        ]);
        $cartItem->setRelation('product', $product);

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->name = 'Mag Plan';
        $plan->currency = 'GBP';
        $plan->pricingTiers = collect([]);

        // subscription_plan_id is set so repository WILL be called
        $this->subscriptionPlanRepository->shouldReceive('find')
            ->once()
            ->with(42)
            ->andReturn($plan);

        $this->cartRepository->shouldReceive('findBySessionOrUser')
            ->once()
            ->andReturn(collect([$cartItem]));

        $items = $this->service->getItems();

        $this->assertCount(1, $items);
        $this->assertEquals(42, $items[0]['subscription_plan_id']);
        $this->assertEquals(CartItemType::SUBSCRIPTION_BUNDLE->value, $items[0]['item_type']);
        $this->assertEquals(7, $items[0]['options']['bundle_id'] ?? null);
        $this->assertEquals('Mag Plan', $items[0]['plan_name']);
        $this->assertEquals('GBP', $items[0]['currency']);
    }

    // -----------------------------------------------------------------------
    // Checkout compatibility: items appear as subscription items
    // -----------------------------------------------------------------------

    public function test_contains_subscription_bundle_items_returns_true_when_bundle_item_present(): void
    {
        $cart = new CartItem([
            'subscription_plan_id' => 1,
            'options' => json_encode(['bundle_id' => 5, 'delivery_type' => 'digital']),
        ]);

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->name = 'Test Plan';
        $plan->currency = 'GBP';
        $plan->pricingTiers = collect([]);

        $this->subscriptionPlanRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($plan);

        $this->cartRepository->shouldReceive('findBySessionOrUser')
            ->andReturn(collect([$cart]));

        $this->assertTrue($this->service->containsSubscriptionBundleItems());
    }

    public function test_contains_subscription_bundle_items_returns_false_when_no_bundle_items(): void
    {
        $cart = new CartItem([
            'subscription_plan_id' => 1,
            'options' => json_encode(['delivery_type' => 'digital']),
        ]);

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->name = 'Test Plan';
        $plan->currency = 'GBP';
        $plan->pricingTiers = collect([]);

        $this->subscriptionPlanRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($plan);

        $this->cartRepository->shouldReceive('findBySessionOrUser')
            ->andReturn(collect([$cart]));

        $this->assertFalse($this->service->containsSubscriptionBundleItems());
    }

    public function test_contains_subscription_bundle_items_returns_false_for_empty_cart(): void
    {
        $this->cartRepository->shouldReceive('findBySessionOrUser')
            ->andReturn(collect([]));

        $this->assertFalse($this->service->containsSubscriptionBundleItems());
    }

    public function test_contains_subscription_bundle_items_handles_array_options(): void
    {
        $cart = new CartItem([
            'subscription_plan_id' => 1,
            'options' => json_encode(['bundle_id' => 3, 'delivery_type' => 'print']),
        ]);

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->name = 'Test Plan';
        $plan->currency = 'GBP';
        $plan->pricingTiers = collect([]);

        $this->subscriptionPlanRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($plan);

        $this->cartRepository->shouldReceive('findBySessionOrUser')
            ->andReturn(collect([$cart]));

        $this->assertTrue($this->service->containsSubscriptionBundleItems());
    }

    public function test_contains_subscription_bundle_items_returns_true_when_mixed_cart_has_bundle(): void
    {
        $cart1 = new CartItem([
            'subscription_plan_id' => 1,
            'options' => json_encode(['delivery_type' => 'print']),
        ]);

        $cart2 = new CartItem([
            'subscription_plan_id' => 2,
            'options' => json_encode(['bundle_id' => 3, 'delivery_type' => 'print']),
        ]);

        $plan1 = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan1->name = 'Plan 1';
        $plan1->currency = 'GBP';
        $plan1->pricingTiers = collect([]);

        $plan2 = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan2->name = 'Plan 2';
        $plan2->currency = 'GBP';
        $plan2->pricingTiers = collect([]);

        $this->subscriptionPlanRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($plan1);

        $this->subscriptionPlanRepository->shouldReceive('find')
            ->with(2)
            ->once()
            ->andReturn($plan2);

        $this->cartRepository->shouldReceive('findBySessionOrUser')
            ->andReturn(collect([$cart1, $cart2]));

        $this->assertTrue($this->service->containsSubscriptionBundleItems());
    }

    public function test_get_price_for_subscription_returns_plan_price_when_no_tier(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->price = 29.99;

        $price = $this->callPrivate('getPriceForSubscription', [$plan, null, 'print']);

        $this->assertEquals(29.99, $price);
    }

    private function callPrivate(string $method, array $args): mixed
    {
        $ref = new ReflectionMethod($this->service, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($this->service, $args);
    }

    public function test_get_price_for_subscription_uses_digital_sale_price_when_lower(): void
    {
        $tier = new SubscriptionPlanPricing(['digital_price' => 20, 'digital_sale_price' => 15, 'price' => 30]);

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();

        $price = $this->callPrivate('getPriceForSubscription', [$plan, $tier, 'digital']);

        $this->assertEquals(15.00, $price);
    }

    public function test_get_price_for_subscription_ignores_digital_sale_price_when_not_lower(): void
    {
        $tier = new SubscriptionPlanPricing(['digital_price' => 20, 'digital_sale_price' => 25, 'price' => 30]);

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();

        $price = $this->callPrivate('getPriceForSubscription', [$plan, $tier, 'digital']);

        $this->assertEquals(20.00, $price);
    }

    public function test_get_price_for_subscription_falls_back_to_print_price_when_digital_price_null(): void
    {
        // BUG FIX: previously returned null here, causing downstream arithmetic errors
        $tier = new SubscriptionPlanPricing(['digital_price' => null, 'digital_sale_price' => null, 'price' => 30]);

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();

        $price = $this->callPrivate('getPriceForSubscription', [$plan, $tier, 'digital']);

        $this->assertEquals(30.00, $price);
        $this->assertIsFloat($price); // must never be null
    }

    public function test_get_price_for_subscription_uses_print_sale_price_when_lower(): void
    {
        $tier = new SubscriptionPlanPricing(['sale_price' => 24.99, 'price' => 30]);

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();

        $price = $this->callPrivate('getPriceForSubscription', [$plan, $tier, 'print']);

        $this->assertEquals(24.99, $price);
    }

    public function test_get_price_for_subscription_ignores_print_sale_price_when_not_lower(): void
    {
        $tier = new SubscriptionPlanPricing(['sale_price' => 35, 'price' => 30]);

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();

        $price = $this->callPrivate('getPriceForSubscription', [$plan, $tier, 'print']);

        $this->assertEquals(30.00, $price);
    }

    public function test_get_price_for_subscription_returns_float_not_null_for_digital_with_no_prices(): void
    {
        $tier = new SubscriptionPlanPricing(['digital_price' => null, 'digital_sale_price' => null, 'price' => 25]);

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();

        $price = $this->callPrivate('getPriceForSubscription', [$plan, $tier, 'digital']);

        $this->assertIsFloat($price);
        $this->assertGreaterThan(0, $price);
    }

    public function testUpdateQuantityForDigitalSubscriptionSucceeds(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->print_shipping_required = false;

        $policy = Mockery::mock(AvailabilityPolicyInterface::class);
        $policy->shouldReceive('canPurchase')->andReturn(true);
        $plan->shouldReceive('availabilityPolicy')->andReturn($policy);

        $cartItem = Mockery::mock(CartItem::class)->makePartial();
        $cartItem->subscription_plan_id = 1;
        //$cartItem->shouldReceive('getAttribute')->with('subscription_plan_id')->andReturn(1);
        // $cartItem->shouldReceive('getAttribute')->with('price')->andReturn(9.99);
        $cartItem->price = 9.99;

        $this->cartRepository->shouldReceive('findById')->once()->andReturn($cartItem);
        $this->subscriptionPlanRepository->shouldReceive('find')->with(1)->andReturn($plan);
        $this->cartRepository->shouldReceive('update')
            ->once()
            ->with(1, Mockery::on(fn($d) => $d['quantity'] === 2 && $d['subtotal'] === 19.98));

        $result = $this->service->updateQuantity(1, 2);

        $this->assertTrue($result['success']);
        $this->assertEquals('Cart updated', $result['message']);
    }

    public function testUpdateQuantityForSubscriptionFailsWhenPlanUnavailable(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->print_shipping_required = false;

        $policy = Mockery::mock(AvailabilityPolicyInterface::class);
        $policy->shouldReceive('canPurchase')->andReturn(false);
        $plan->shouldReceive('availabilityPolicy')->andReturn($policy);

        $cartItem = Mockery::mock(CartItem::class)->makePartial();
        $cartItem->subscription_plan_id = 1;
        $cartItem->shouldReceive('getAttribute')->with('subscription_plan_id')->andReturn(1);

        $this->cartRepository->shouldReceive('findById')->once()->andReturn($cartItem);
        $this->subscriptionPlanRepository->shouldReceive('find')->with(1)->andReturn($plan);

        $result = $this->service->updateQuantity(1, 2);

        $this->assertFalse($result['success']);
        $this->assertEquals('Subscription is no longer available for purchase', $result['message']);
    }

    public function testUpdateQuantityForPrintSubscriptionFailsWhenIssueOutOfStock(): void
    {
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->stock_quantity = 1;

        $issuePolicy = Mockery::mock(AvailabilityPolicyInterface::class);
        $issuePolicy->shouldReceive('canPurchase')->andReturn(true);
        $issuePolicy->shouldReceive('isPreOrder')->andReturn(false);
        $issue->shouldReceive('availabilityPolicy')->andReturn($issuePolicy);

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->print_shipping_required = true;

        $policy = Mockery::mock(AvailabilityPolicyInterface::class);
        $policy->shouldReceive('canPurchase')->andReturn(true);
        $plan->shouldReceive('availabilityPolicy')->andReturn($policy);
        $plan->shouldReceive('getNextIssue')->andReturn($issue);

        $cartItem = Mockery::mock(CartItem::class)->makePartial();
        $cartItem->subscription_plan_id = 1;
        $cartItem->shouldReceive('getAttribute')->with('subscription_plan_id')->andReturn(1);

        $this->cartRepository->shouldReceive('findById')->once()->andReturn($cartItem);
        $this->subscriptionPlanRepository->shouldReceive('find')->with(1)->andReturn($plan);

        $result = $this->service->updateQuantity(1, 3);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('1 in stock', $result['message']);
    }

    public function testUpdateQuantityForPrintSubscriptionAllowsPreorderQuantity(): void
    {
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->stock_quantity = 0;

        $issuePolicy = Mockery::mock(AvailabilityPolicyInterface::class);
        $issuePolicy->shouldReceive('canPurchase')->andReturn(true);
        $issuePolicy->shouldReceive('isPreOrder')->andReturn(true);
        $issue->shouldReceive('availabilityPolicy')->andReturn($issuePolicy);

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->print_shipping_required = true;

        $policy = Mockery::mock(AvailabilityPolicyInterface::class);
        $policy->shouldReceive('canPurchase')->andReturn(true);
        $plan->shouldReceive('availabilityPolicy')->andReturn($policy);
        $plan->shouldReceive('getNextIssue')->andReturn($issue);

        $cartItem = Mockery::mock(CartItem::class)->makePartial();
        $cartItem->subscription_plan_id = 1;
        $cartItem->shouldReceive('getAttribute')->with('subscription_plan_id')->andReturn(1);
        $cartItem->shouldReceive('getAttribute')->with('price')->andReturn(9.99);
        $cartItem->price = 9.99;

        $this->cartRepository->shouldReceive('findById')->once()->andReturn($cartItem);
        $this->subscriptionPlanRepository->shouldReceive('find')->with(1)->andReturn($plan);
        $this->cartRepository->shouldReceive('update')->once();

        $result = $this->service->updateQuantity(1, 2);

        $this->assertTrue($result['success']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->cartRepository = Mockery::mock(CartRepository::class);
        $this->productRepository = Mockery::mock(ProductRepository::class);
        $this->subscriptionPlanRepository = Mockery::mock(SubscriptionPlanRepository::class);
        $this->offerRepository = Mockery::mock(ProductOfferRepository::class);
        $this->bundleRepository = Mockery::mock(ProductOfferBundleRepository::class);
        $this->voucherService = Mockery::mock(VoucherService::class);
        $this->productVariantRepository = Mockery::mock(ProductVariantRepository::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->subscriptionBundleRepository = Mockery::mock(SubscriptionBundleRepository::class);
        $this->subscriptionBundlePriceAllocator = Mockery::mock(SubscriptionBundlePriceAllocator::class);

        // Real collaborators
        $this->stockResolver = new CartStockResolver();
        $this->priceResolver = new CartPriceResolver();
        $this->itemFactory = new CartItemFactory();
        $this->shippingService = new ShippingService();

        $this->service = new CartService(
            $this->cartRepository,
            $this->productRepository,
            $this->subscriptionPlanRepository,
            $this->offerRepository,
            $this->bundleRepository,
            $this->voucherService,
            $this->productVariantRepository,
            $this->databaseMock,
            $this->stockResolver,
            $this->priceResolver,
            $this->itemFactory,
            $this->shippingService,
            $this->subscriptionBundleRepository,
            $this->subscriptionBundlePriceAllocator
        );

        $_SESSION['cart_session_id'] = 'test_session_123';
    }


    // Helper — calls a private method via reflection (add to CartServiceTest base)

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}