<?php

namespace App\Tests\Unit\Services\Shopping\Factories;

use App\DTO\Cart\CartItemData;
use App\Enums\CartItemType;
use App\Enums\Subscriptions\SubscriptionType;
use App\Models\Product;
use App\Services\Shopping\Factories\CartItemFactory;
use App\Tests\Functional\Controllers\FunctionalTestCase;

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

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new CartItemFactory();
    }
}