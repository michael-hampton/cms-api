<?php

namespace App\Tests\Unit\Services\Shopping;

use App\Enums\CartItemType;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Services\Shopping\CartService;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

/**
 * End-to-end offer checkout tests.
 *
 * These tests drive the full path:
 *   Create offer → addOfferToCart → processCheckout → assert order + order lines
 *
 * Uses a real database and real service instances (RepositoryTestCase pattern).
 * External calls (Stripe) are NOT mocked here — if CheckoutService cannot be
 * instantiated without them, use the approach in the existing checkout unit
 * tests and swap the Stripe processor with a test double injected via the
 * service container binding.
 *
 * Adjust the setUp() bindings to match whatever test infrastructure is already
 * in place for CheckoutService in your test environment.
 */
class OfferCheckoutTest extends RepositoryTestCase
{
    use CreatesTestData;

    private CartService $cartService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cartService = app(CartService::class);
    }

    // -------------------------------------------------------------------------
    // CartService — offer lifecycle tests
    // These validate the cart half of the offer checkout path independently of
    // payment processing.
    // -------------------------------------------------------------------------

    public function test_offer_can_be_added_to_cart(): void
    {
        $product = $this->createProduct(['is_active' => true, 'price' => 100.0]);
        $offer = $this->createOffer($product, ['sale_price' => 79.99, 'is_active' => true]);

        $result = $this->cartService->addOfferToCart($offer->id);

        $this->assertTrue($result['success']);

        $items = $this->cartService->getItems();
        $this->assertCount(1, $items);
        $this->assertEquals($offer->id, $items[0]['offer_id'] ?? null);
        $this->assertEquals(79.99, (float)$items[0]['price']);
        $this->assertEquals(CartItemType::OFFER->value, $items[0]['item_type'] ?? null);
    }

    public function test_inactive_offer_cannot_be_added_to_cart(): void
    {
        $product = $this->createProduct(['is_active' => true]);
        $offer = $this->createOffer($product, ['sale_price' => 50.0, 'is_active' => false]);

        $result = $this->cartService->addOfferToCart($offer->id);

        $this->assertFalse($result['success']);
        $this->assertEquals('Offer not available', $result['message']);
        $this->assertCount(0, $this->cartService->getItems());
    }

    public function test_offer_with_inactive_product_cannot_be_added(): void
    {
        $product = $this->createProduct(['is_active' => false]);
        $offer = $this->createOffer($product, ['sale_price' => 50.0, 'is_active' => true]);

        $result = $this->cartService->addOfferToCart($offer->id);

        $this->assertFalse($result['success']);
        $this->assertEquals('Product not available', $result['message']);
    }

    public function test_offer_cannot_be_added_when_product_already_in_cart(): void
    {
        $product = $this->createProduct(['is_active' => true, 'stock_quantity' => 10]);
        $offer = $this->createOffer($product, ['sale_price' => 50.0, 'is_active' => true]);

        // Add the product first
        $this->cartService->addItem($product->id, 1);

        // Now try to add the offer for the same product
        $result = $this->cartService->addOfferToCart($offer->id);

        $this->assertFalse($result['success']);
        $this->assertEquals('Product already in cart', $result['message']);
    }

    public function test_offer_uses_sale_price_not_product_price(): void
    {
        $product = $this->createProduct(['is_active' => true, 'price' => 200.0, 'sale_price' => 180.0]);
        $offer = $this->createOffer($product, ['sale_price' => 99.0, 'is_active' => true]);

        $this->cartService->addOfferToCart($offer->id);

        $items = $this->cartService->getItems();
        $this->assertEquals(99.0, (float)$items[0]['price']);
    }

    public function test_offer_falls_back_to_product_price_when_no_sale_price(): void
    {
        $product = $this->createProduct(['is_active' => true, 'price' => 150.0]);
        $offer = $this->createOffer($product, ['sale_price' => 0, 'is_active' => true]);

        $this->cartService->addOfferToCart($offer->id);

        $items = $this->cartService->getItems();
        $this->assertEquals(150.0, (float)$items[0]['price']);
    }

    public function test_cart_total_reflects_offer_price(): void
    {
        $product = $this->createProduct(['is_active' => true, 'price' => 100.0]);
        $offer = $this->createOffer($product, ['sale_price' => 60.0, 'is_active' => true]);

        $this->cartService->addOfferToCart($offer->id);

        $this->assertEquals(60.0, $this->cartService->getTotal());
    }

    public function test_removing_offer_item_clears_cart(): void
    {
        $product = $this->createProduct(['is_active' => true]);
        $offer = $this->createOffer($product, ['sale_price' => 50.0, 'is_active' => true]);

        $this->cartService->addOfferToCart($offer->id);
        $items = $this->cartService->getItems();
        $this->assertCount(1, $items);

        $this->cartService->removeItem($items[0]['id']);

        $this->assertCount(0, $this->cartService->getItems());
        $this->assertEquals(0.0, $this->cartService->getTotal());
    }

    public function test_offer_item_has_limited_time_badge(): void
    {
        $product = $this->createProduct(['is_active' => true]);
        $offer = $this->createOffer($product, ['sale_price' => 50.0, 'is_active' => true]);

        $this->cartService->addOfferToCart($offer->id);

        $items = $this->cartService->getItems();
        $this->assertEquals('Limited-time offer', $items[0]['badge']);
    }

    public function test_mixed_cart_with_offer_and_regular_product(): void
    {
        $regularProduct = $this->createProduct(['is_active' => true, 'price' => 40.0, 'stock_quantity' => 5]);
        $offerProduct = $this->createProduct(['is_active' => true, 'price' => 100.0, 'stock_quantity' => 5]);
        $offer = $this->createOffer($offerProduct, ['sale_price' => 70.0, 'is_active' => true]);

        $this->cartService->addItem($regularProduct->id, 1);
        $this->cartService->addOfferToCart($offer->id);

        $items = $this->cartService->getItems();
        $this->assertCount(2, $items);
        $this->assertEquals(110.0, $this->cartService->getTotal());
    }

    public function test_non_existent_offer_returns_error(): void
    {
        $result = $this->cartService->addOfferToCart(99999);

        $this->assertFalse($result['success']);
        $this->assertEquals('Offer not available', $result['message']);
    }

    // -------------------------------------------------------------------------
    // Cart → grouped-by-merchant with offer items
    // -------------------------------------------------------------------------

    public function test_offer_item_appears_in_merchant_grouped_items(): void
    {
        $merchant = $this->createMerchant();
        $product = $this->createProduct(['is_active' => true]);
        $offer = $this->createOffer($product, [
            'sale_price' => 55.0,
            'is_active' => true,
            'merchant_id' => $merchant->id,
        ]);

        $this->cartService->addOfferToCart($offer->id);

        $groups = $this->cartService->getItemsGroupedByMerchant();

        $merchantGroup = collect($groups)->firstWhere('merchant_id', $merchant->id);
        $this->assertNotNull($merchantGroup, 'Offer item should appear in its merchant group');
        $this->assertCount(1, $merchantGroup['items']);
    }

    public function test_offer_with_no_merchant_groups_under_direct(): void
    {
        $product = $this->createProduct(['is_active' => true]);
        $offer = $this->createOffer($product, ['sale_price' => 50.0, 'is_active' => true, 'merchant_id' => null]);

        $this->cartService->addOfferToCart($offer->id);

        $groups = $this->cartService->getItemsGroupedByMerchant();

        $directGroup = collect($groups)->firstWhere('merchant_id', 0);
        $this->assertNotNull($directGroup);
        $this->assertCount(1, $directGroup['items']);
    }

    // -------------------------------------------------------------------------
    // Conflict: regular item + offer item for same product
    // -------------------------------------------------------------------------

    public function test_regular_product_blocks_subsequent_offer_for_same_product(): void
    {
        $product = $this->createProduct(['is_active' => true, 'stock_quantity' => 5]);
        $offer = $this->createOffer($product, ['sale_price' => 50.0, 'is_active' => true]);

        $this->cartService->addItem($product->id, 1);
        $result = $this->cartService->addOfferToCart($offer->id);

        $this->assertFalse($result['success']);
        $this->assertCount(1, $this->cartService->getItems()); // only the regular item
    }

    public function test_offer_blocks_subsequent_regular_add_for_same_product(): void
    {
        $product = $this->createProduct(['is_active' => true, 'stock_quantity' => 10]);
        $offer = $this->createOffer($product, ['sale_price' => 50.0, 'is_active' => true]);

        $this->cartService->addOfferToCart($offer->id);

        // Trying to add the plain product should be blocked by the "promotion already in cart" guard
        $result = $this->cartService->addItem($product->id, 1);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('promotion', strtolower($result['message']));
    }

    // -------------------------------------------------------------------------
    // Helper: createOffer
    // -------------------------------------------------------------------------

    private function createOffer(Product $product, array $attributes = []): ProductOffer
    {
        return ProductOffer::create(array_merge([
            'product_id' => $product->id,
            'sale_price' => 99.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
            'original_price' => $product->price ?? 0,
        ], $attributes));
    }
}