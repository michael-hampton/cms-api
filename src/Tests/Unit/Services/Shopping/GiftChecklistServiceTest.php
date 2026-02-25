<?php

namespace App\Tests\Unit\Services\Shopping;

use App\DTO\Cart\GiftChecklistItem;
use App\Enums\CartItemType;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Shopping\CartRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Services\Shopping\CartService;
use App\Services\Shopping\Factories\CartItemFactory;
use App\Services\Shopping\GiftChecklistService;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

/**
 * Integration tests for GiftChecklistService.
 *
 * Uses a real database (RepositoryTestCase) so that cart operations are
 * exercised end-to-end — the same pattern as the rest of the shopping tests.
 */
class GiftChecklistServiceTest extends RepositoryTestCase
{
    use CreatesTestData;

    private GiftChecklistService $service;
    private CartService $cartService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cartService = app(CartService::class);
        $this->service = new GiftChecklistService(
            cartRepository: app(CartRepository::class),
            cartItemFactory: app(CartItemFactory::class),
            productRepository: app(ProductRepository::class),
            subscriptionPlanRepository: app(SubscriptionPlanRepository::class),
        );
    }

    // -------------------------------------------------------------------------
    // Product gift
    // -------------------------------------------------------------------------

    public function test_add_product_gift_creates_zero_price_cart_item(): void
    {
        $product = $this->createProduct(['is_active' => true]);

        $gift = new GiftChecklistItem(label: 'Free T-Shirt', productId: $product->id);
        $result = $this->service->addGift($gift);

        $this->assertTrue($result['success']);

        $gifts = $this->service->getGiftsInCart();
        $this->assertCount(1, $gifts);

        $cartItem = $gifts[0];

        $options = is_string($cartItem['options']) ? json_decode($cartItem['options'], true) : (array)$cartItem['options'];

        $this->assertEquals(0.0, (float)$cartItem->price);
        $this->assertEquals(CartItemType::FREE_GIFT->value, $options['type']);
        $this->assertTrue($options['is_gift']);
        $this->assertEquals($product->id, $options['product_id']);
        $this->assertEquals('Free T-Shirt', $options['label']);
    }

    public function test_add_product_gift_rejects_inactive_product(): void
    {
        $product = $this->createProduct(['is_active' => false]);

        $gift = new GiftChecklistItem(label: 'Inactive Gift', productId: $product->id);
        $result = $this->service->addGift($gift);

        $this->assertFalse($result['success']);
        $this->assertEquals('Gift product is not available', $result['message']);
        $this->assertCount(0, $this->service->getGiftsInCart());
    }

    public function test_add_product_gift_prevents_duplicate(): void
    {
        $product = $this->createProduct(['is_active' => true]);

        $gift = new GiftChecklistItem(label: 'Duplicate Gift', productId: $product->id);

        $first = $this->service->addGift($gift);
        $second = $this->service->addGift($gift);

        $this->assertTrue($first['success']);
        $this->assertFalse($second['success']);
        $this->assertEquals('This gift is already in your cart', $second['message']);
        $this->assertCount(1, $this->service->getGiftsInCart());
    }

    public function test_two_different_product_gifts_are_both_added(): void
    {
        $product1 = $this->createProduct(['is_active' => true]);
        $product2 = $this->createProduct(['is_active' => true]);

        $this->service->addGift(new GiftChecklistItem(label: 'Gift A', productId: $product1->id));
        $this->service->addGift(new GiftChecklistItem(label: 'Gift B', productId: $product2->id));

        $this->assertCount(2, $this->service->getGiftsInCart());
    }

    // -------------------------------------------------------------------------
    // Subscription gift
    // -------------------------------------------------------------------------

    public function test_add_subscription_gift_creates_zero_price_cart_item(): void
    {
        $plan = $this->createSubscriptionPlan(['is_active' => true]);

        $gift = new GiftChecklistItem(label: 'Free Digital Sub', subscriptionPlanId: $plan->id, deliveryType: 'digital');
        $result = $this->service->addGift($gift);

        $this->assertTrue($result['success']);

        $gifts = $this->service->getGiftsInCart();
        $options = is_string($gifts[0]['options']) ? json_decode($gifts[0]['options'], true) : (array)$gifts[0]['options'];

        $this->assertEquals(0.0, (float)$gifts[0]->price);
        $this->assertEquals(CartItemType::FREE_GIFT->value, $options['type']);
        $this->assertTrue($options['is_gift']);
        $this->assertEquals($plan->id, $options['subscription_plan_id']);
        $this->assertEquals('digital', $options['delivery_type']);
        $this->assertNull($options['product_id']);
    }

    public function test_add_subscription_gift_rejects_missing_plan(): void
    {
        $gift = new GiftChecklistItem(label: 'Ghost Plan', subscriptionPlanId: 99999);
        $result = $this->service->addGift($gift);

        $this->assertFalse($result['success']);
        $this->assertEquals('Gift subscription plan is not available', $result['message']);
    }

    public function test_add_subscription_gift_prevents_duplicate(): void
    {
        $plan = $this->createSubscriptionPlan(['is_active' => true]);
        $gift = new GiftChecklistItem(label: 'Sub Gift', subscriptionPlanId: $plan->id);

        $first = $this->service->addGift($gift);
        $second = $this->service->addGift($gift);

        $this->assertTrue($first['success']);
        $this->assertFalse($second['success']);
        $this->assertCount(1, $this->service->getGiftsInCart());
    }

    // -------------------------------------------------------------------------
    // Removal
    // -------------------------------------------------------------------------

    public function test_remove_gift_removes_only_the_target_item(): void
    {
        $product = $this->createProduct(['is_active' => true]);
        $plan = $this->createSubscriptionPlan(['is_active' => true]);

        $this->service->addGift(new GiftChecklistItem(label: 'Product Gift', productId: $product->id));
        $this->service->addGift(new GiftChecklistItem(label: 'Sub Gift', subscriptionPlanId: $plan->id));

        $gifts = $this->service->getGiftsInCart();

        $this->assertCount(2, $gifts);

        $result = $this->service->removeGift($gifts[0]['id']);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $this->service->getGiftsInCart());
    }

    public function test_remove_gift_returns_error_for_unknown_item(): void
    {
        $result = $this->service->removeGift(99999);

        $this->assertFalse($result['success']);
        $this->assertEquals('Gift item not found', $result['message']);
    }

    public function test_remove_gift_refuses_to_remove_non_gift_items(): void
    {
        // Add a regular (non-gift) product to the cart via CartService
        $product = $this->createProduct(['is_active' => true, 'stock_quantity' => 100]);
        $this->cartService->addItem($product->id, 1);

        $cartItems = $this->cartService->getItems();

        $this->assertNotEmpty($cartItems);

        $regularItemId = $cartItems[0]['id'];
        $result = $this->service->removeGift($regularItemId);

        $this->assertFalse($result['success']);
        $this->assertEquals('Item is not a gift', $result['message']);
    }

    // -------------------------------------------------------------------------
    // getGiftsInCart
    // -------------------------------------------------------------------------

    public function test_get_gifts_returns_empty_array_when_no_gifts(): void
    {
        $this->assertSame([], $this->service->getGiftsInCart());
    }

    public function test_get_gifts_only_returns_gift_items_not_regular_products(): void
    {
        $product = $this->createProduct(['is_active' => true, 'stock_quantity' => 100]);
        $giftProduct = $this->createProduct(['is_active' => true, 'stock_quantity' => 100]);

        // Add one regular item and one gift
        $this->cartService->addItem($product->id, 1);
        $this->service->addGift(new GiftChecklistItem(label: 'Free Gift', productId: $giftProduct->id));

        $gifts = $this->service->getGiftsInCart();

        $this->assertCount(1, $gifts);
        $options = is_string($gifts[0]['options']) ? json_decode($gifts[0]['options'], true) : (array)$gifts[0]['options'];
        $this->assertEquals($giftProduct->id, $options['product_id']);
    }

    // -------------------------------------------------------------------------
    // GiftChecklistItem DTO validation
    // -------------------------------------------------------------------------

    public function test_gift_dto_throws_when_neither_product_nor_subscription(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new GiftChecklistItem(label: 'Bad Gift');
    }

    public function test_gift_dto_throws_when_both_product_and_subscription_set(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new GiftChecklistItem(
            label: 'Ambiguous Gift',
            productId: 1,
            subscriptionPlanId: 1,
        );
    }

    public function test_gift_dto_metadata_includes_is_gift_flag(): void
    {
        $gift = new GiftChecklistItem(label: 'T-Shirt', productId: 5);

        $metadata = $gift->toMetadata();

        $this->assertTrue($metadata['is_gift']);
        $this->assertEquals(CartItemType::FREE_GIFT->value, $metadata['type']);
        $this->assertEquals('T-Shirt', $metadata['label']);
        $this->assertEquals(5, $metadata['product_id']);
        $this->assertNull($metadata['subscription_plan_id']);
    }

    public function test_gift_dto_metadata_for_subscription(): void
    {
        $gift = new GiftChecklistItem(
            label: 'Digital Sub',
            subscriptionPlanId: 10,
            deliveryType: 'digital',
        );

        $metadata = $gift->toMetadata();

        $this->assertTrue($metadata['is_gift']);
        $this->assertEquals(10, $metadata['subscription_plan_id']);
        $this->assertNull($metadata['product_id']);
        $this->assertEquals('digital', $metadata['delivery_type']);
    }
}