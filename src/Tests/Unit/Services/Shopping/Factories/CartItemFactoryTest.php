<?php

namespace App\Tests\Unit\Services\Shopping\Factories;

use App\DTO\Cart\CartItemData;
use App\Enums\CartItemType;
use App\Enums\Subscriptions\SubscriptionType;
use App\Models\Product;
use App\Models\SubscriptionPlan;
use App\Services\Shopping\Factories\CartItemFactory;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class CartItemFactoryTest extends FunctionalTestCase
{
    private CartItemFactory $factory;

    public function testFromProductReturnsCartItemData(): void
    {
        $product = new Product([
            'id' => 1,
            'site_id' => 10
        ]);

        $result = $this->factory->fromProduct(
            'session123',
            42,
            $product,
            2,
            99.99
        );

        $this->assertInstanceOf(CartItemData::class, $result);
        $this->assertEquals('session123', $result->session_id);
        $this->assertEquals(42, $result->user_id);
        $this->assertEquals(1, $result->product_id);
        $this->assertEquals(2, $result->quantity);
        $this->assertEquals(99.99, $result->price);
        $this->assertEquals(199.98, $result->subtotal);
        $this->assertEquals(10, $result->site_id);
        $this->assertNull($result->merchant_id);
        $this->assertNull($result->variant_id);
        $this->assertIsArray($result->options);
        $this->assertEmpty($result->options);
    }

    public function testFromProductWithVariantAndMerchant(): void
    {
        $product = new Product([
            'id' => 1,
            'site_id' => 10
        ]);

        $result = $this->factory->fromProduct(
            'session123',
            42,
            $product,
            1,
            79.99,
            ['color' => 'blue'],
            5,
            99
        );

        $this->assertEquals(5, $result->variant_id);
        $this->assertEquals(99, $result->merchant_id);
        $this->assertIsArray($result->options);
        $this->assertEquals(['color' => 'blue'], $result->options);
    }

    public function testFromProductToArraySerializesOptions(): void
    {
        $product = new Product([
            'id' => 1,
            'site_id' => 10
        ]);

        $dto = $this->factory->fromProduct(
            'session123',
            42,
            $product,
            2,
            99.99,
            ['color' => 'blue']
        );

        $array = $dto->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('session123', $array['session_id']);
        $this->assertEquals(42, $array['user_id']);
        $this->assertIsString($array['options']);
        $this->assertEquals('{"color":"blue"}', $array['options']);
    }

    public function testFromOfferReturnsCartItemData(): void
    {
        $product = new Product([
            'id' => 1,
            'site_id' => 10
        ]);

        $result = $this->factory->fromOffer(
            'session123',
            42,
            $product,
            1,
            59.99,
            777,
            88
        );

        $this->assertInstanceOf(CartItemData::class, $result);
        $this->assertEquals(1, $result->product_id);
        $this->assertEquals(59.99, $result->price);
        $this->assertEquals(88, $result->merchant_id);
        $this->assertNull($result->variant_id);

        $this->assertIsArray($result->options);
        $this->assertEquals(CartItemType::OFFER->value, $result->options['type']);
        $this->assertEquals(777, $result->options['offer_id']);
    }

    public function testFromBundleReturnsCartItemData(): void
    {
        $product = new Product([
            'id' => 1,
            'site_id' => 10
        ]);

        $result = $this->factory->fromBundle(
            'session123',
            42,
            $product,
            3,
            29.99,
            555,
            66
        );

        $this->assertInstanceOf(CartItemData::class, $result);
        $this->assertEquals(3, $result->quantity);
        $this->assertEquals(89.97, $result->subtotal);
        $this->assertNull($result->merchant_id);  // Never stored at row level
        $this->assertNull($result->variant_id);

        $this->assertIsArray($result->options);
        $this->assertEquals(CartItemType::BUNDLE->value, $result->options['type']);
        $this->assertEquals(555, $result->options['bundle_id']);
        $this->assertEquals(66, $result->options['merchant_id']);  // In options for reference
    }

    public function testFromSubscriptionReturnsCartItemData(): void
    {
        $product = new Product([
            'id' => 1,
            'site_id' => 10
        ]);

        $result = $this->factory->fromSubscription(
            'session123',
            42,
            $product,
            1,
            19.99,
            333,
            SubscriptionType::DIGITAL->value
        );

        $this->assertInstanceOf(CartItemData::class, $result);
        $this->assertEquals(1, $result->product_id);
        $this->assertEquals(19.99, $result->price);
        $this->assertEquals(333, $result->subscription_plan_id);
        $this->assertNull($result->merchant_id);
        $this->assertNull($result->variant_id);

        $this->assertIsArray($result->options);
        $this->assertEquals(CartItemType::SUBSCRIPTION->value, $result->options['type']);
        $this->assertEquals(SubscriptionType::DIGITAL->value, $result->options['delivery_type']);
    }

    public function testCartItemDataIsReadonly(): void
    {
        $product = new Product([
            'id' => 1,
            'site_id' => 10
        ]);

        $dto = $this->factory->fromProduct(
            'session123',
            42,
            $product,
            2,
            99.99
        );

        // Readonly properties should not be modifiable
        $this->expectException(\Error::class);
        $dto->quantity = 5;
    }

    public function testCartItemDataHelperMethods(): void
    {
        $product = new Product([
            'id' => 1,
            'site_id' => 10
        ]);

        $offerDto = $this->factory->fromOffer(
            'session123',
            42,
            $product,
            1,
            59.99,
            777,
            88
        );

        $this->assertTrue($offerDto->isOffer());
        $this->assertFalse($offerDto->isBundle());
        $this->assertFalse($offerDto->isSubscription());

        $bundleDto = $this->factory->fromBundle(
            'session123',
            42,
            $product,
            1,
            29.99,
            555,
            66
        );

        $this->assertFalse($bundleDto->isOffer());
        $this->assertTrue($bundleDto->isBundle());
        $this->assertFalse($bundleDto->isSubscription());
    }

    public function testFromSubscriptionBundleItemReturnsCartItemData(): void
    {
        $product = new Product([
            'id' => 1,
            'site_id' => 10,
        ]);

        $result = $this->factory->fromSubscriptionBundleItem(
            'session123',
            42,
            $product,
            1,
            30.00, // allocated price
            333,   // subscriptionPlanId
            SubscriptionType::DIGITAL->value,
            7      // bundleId
        );

        $this->assertInstanceOf(CartItemData::class, $result);
        $this->assertEquals(30.00, $result->price);
        $this->assertEquals(333, $result->subscription_plan_id);
        $this->assertNull($result->merchant_id);
        $this->assertNull($result->variant_id);

        $this->assertIsArray($result->options);
        $this->assertEquals(CartItemType::SUBSCRIPTION_BUNDLE->value, $result->options['type']);
        $this->assertEquals(7, $result->options['bundle_id']);
        $this->assertEquals(333, $result->options['subscription_plan_id']);
        $this->assertEquals(SubscriptionType::DIGITAL->value, $result->options['delivery_type']);
    }

    public function testFromSubscriptionBundleItemToArraySerializesOptions(): void
    {
        $product = new Product(['id' => 1, 'site_id' => 10]);

        $dto = $this->factory->fromSubscriptionBundleItem(
            'session123', 42, $product, 1, 25.00, 55, 'print', 3
        );
        $array = $dto->toArray();

        $this->assertIsString($array['options']);
        $decoded = json_decode($array['options'], true);

        $this->assertEquals(CartItemType::SUBSCRIPTION_BUNDLE->value, $decoded['type']);
        $this->assertEquals(3, $decoded['bundle_id']);
    }

    public function testFromSubscriptionBundleItemHelperMethodReturnsCorrectType(): void
    {
        $product = new Product(['id' => 1, 'site_id' => 10]);

        $dto = $this->factory->fromSubscriptionBundleItem(
            'session123', 42, $product, 1, 25.00, 55, 'digital', 3
        );

        $this->assertFalse($dto->isOffer());
        $this->assertFalse($dto->isBundle());
        $this->assertTrue($dto->isSubscription()); // subscription_bundle IS a subscription
    }

    // -------------------------------------------------------------------------
    // fromGiftProduct
    // -------------------------------------------------------------------------

    public function test_from_gift_product_price_and_subtotal_are_always_zero(): void
    {
        $product = $this->makeProduct();

        $result = $this->factory->fromGiftProduct(
            sessionId: 'session',
            userId: null,
            product: $product,
            quantity: 3,
        );

        $this->assertEquals(0.0, $result->price);
        $this->assertEquals(0.0, $result->subtotal);
    }

    public function test_from_gift_product_quantity_greater_than_one_subtotal_remains_zero(): void
    {
        $product = $this->makeProduct();

        $result = $this->factory->fromGiftProduct(
            sessionId: 'session',
            userId: null,
            product: $product,
            quantity: 5,
        );

        $this->assertEquals(5, $result->quantity);
        $this->assertEquals(0.0, $result->subtotal);
    }

    public function test_from_gift_product_merchant_id_is_always_null(): void
    {
        $product = $this->makeProduct();

        $result = $this->factory->fromGiftProduct(
            sessionId: 'session',
            userId: null,
            product: $product,
            quantity: 1,
        );

        $this->assertNull($result->merchant_id);
    }

    public function test_from_gift_product_uses_product_id_and_site_id_from_model(): void
    {
        $product = $this->makeProduct(id: 77, siteId: 9);

        $result = $this->factory->fromGiftProduct(
            sessionId: 'session',
            userId: null,
            product: $product,
            quantity: 1,
        );

        $this->assertEquals(77, $result->product_id);
        $this->assertEquals(9, $result->site_id);
    }

    public function test_from_gift_product_variant_id_defaults_to_null(): void
    {
        $product = $this->makeProduct();

        $result = $this->factory->fromGiftProduct(
            sessionId: 'session',
            userId: null,
            product: $product,
            quantity: 1,
        );

        $this->assertNull($result->variant_id);
    }

    // -------------------------------------------------------------------------
    // fromGiftSubscription
    // -------------------------------------------------------------------------

    public function test_from_gift_subscription_price_and_subtotal_are_always_zero(): void
    {
        $plan = $this->makePlan();

        $result = $this->factory->fromGiftSubscription(
            sessionId: 'session',
            userId: null,
            plan: $plan,
            quantity: 1,
        );

        $this->assertEquals(0.0, $result->price);
        $this->assertEquals(0.0, $result->subtotal);
    }

    public function test_from_gift_subscription_product_id_is_null(): void
    {
        $plan = $this->makePlan();

        $result = $this->factory->fromGiftSubscription(
            sessionId: 'session',
            userId: null,
            plan: $plan,
            quantity: 1,
        );

        $this->assertNull($result->product_id,
            'Gift subscription has no associated Product — product_id must be null'
        );
    }

    public function test_from_gift_subscription_uses_site_id_from_plan(): void
    {
        $plan = $this->makePlan(id: 12, siteId: 7);

        $result = $this->factory->fromGiftSubscription(
            sessionId: 'session',
            userId: null,
            plan: $plan,
            quantity: 1,
        );

        $this->assertEquals(7, $result->site_id);
    }

    public function test_from_gift_subscription_stores_plan_id_as_subscription_plan_id(): void
    {
        $plan = $this->makePlan(id: 12, siteId: 1);

        $result = $this->factory->fromGiftSubscription(
            sessionId: 'session',
            userId: null,
            plan: $plan,
            quantity: 1,
        );

        $this->assertEquals(12, $result->subscription_plan_id);
    }

    public function test_from_gift_subscription_merchant_id_and_variant_id_are_null(): void
    {
        $plan = $this->makePlan();

        $result = $this->factory->fromGiftSubscription(
            sessionId: 'session',
            userId: null,
            plan: $plan,
            quantity: 1,
        );

        $this->assertNull($result->merchant_id);
        $this->assertNull($result->variant_id);
    }

    public function test_from_gift_subscription_quantity_stored_correctly(): void
    {
        $plan = $this->makePlan();

        $result = $this->factory->fromGiftSubscription(
            sessionId: 'session',
            userId: null,
            plan: $plan,
            quantity: 2,
        );

        $this->assertEquals(2, $result->quantity);
        $this->assertEquals(0.0, $result->subtotal);
    }

    // -------------------------------------------------------------------------
    // Cross-cutting: gift methods never set merchant_id at row level
    // -------------------------------------------------------------------------

    public function test_gift_product_and_subscription_both_have_null_merchant_id(): void
    {
        $product = $this->makeProduct();
        $plan = $this->makePlan();

        $giftProduct = $this->factory->fromGiftProduct('s', null, $product, 1, []);
        $giftSub = $this->factory->fromGiftSubscription('s', null, $plan, 1, []);

        $this->assertNull($giftProduct->merchant_id);
        $this->assertNull($giftSub->merchant_id);
    }

    // -------------------------------------------------------------------------
    // Factories
    // -------------------------------------------------------------------------

    private function makeProduct(int $id = 1, int $siteId = 1): Product|MockInterface
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = $id;
        $product->site_id = $siteId;

        return $product;
    }

    private function makePlan(int $id = 1, int $siteId = 1): SubscriptionPlan|MockInterface
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = $id;
        $plan->site_id = $siteId;

        return $plan;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new CartItemFactory();
    }
}