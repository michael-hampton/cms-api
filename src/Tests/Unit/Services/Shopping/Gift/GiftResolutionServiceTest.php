<?php

namespace App\Tests\Unit\Services\Shopping\Gift;

use App\DTO\Cart\CartContext;
use App\DTO\Cart\GiftLine;
use App\DTO\Cart\PromotionCandidate;
use App\Enums\CartItemType;
use App\Enums\Gifts\GiftQuantityRule;
use App\Enums\Gifts\GiftType;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Shopping\CartRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Services\Shopping\GiftChecklistService;
use App\Services\Shopping\GiftResolutionService;
use App\Services\Shopping\Resolvers\GiftEligibilityCollector;
use App\Services\Shopping\Resolvers\GiftResolutionStrategy;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class GiftResolutionServiceTest extends TestCase
{
    private GiftEligibilityCollector|MockInterface $collector;
    private GiftResolutionStrategy|MockInterface $strategy;
    private GiftChecklistService|MockInterface $giftChecklistService;
    private CartRepository|MockInterface $cartRepository;
    private ProductRepository|MockInterface $productRepository;
    private SubscriptionPlanRepository|MockInterface $subscriptionPlanRepository;

    private GiftResolutionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->collector = Mockery::mock(GiftEligibilityCollector::class);
        $this->strategy = Mockery::mock(GiftResolutionStrategy::class);
        $this->giftChecklistService = Mockery::mock(GiftChecklistService::class);
        $this->cartRepository = Mockery::mock(CartRepository::class);
        $this->productRepository = Mockery::mock(ProductRepository::class);
        $this->subscriptionPlanRepository = Mockery::mock(SubscriptionPlanRepository::class);

        $this->service = new GiftResolutionService(
            collector: $this->collector,
            strategy: $this->strategy,
            giftChecklistService: $this->giftChecklistService,
            cartRepository: $this->cartRepository,
            productRepository: $this->productRepository,
            subscriptionPlanRepository: $this->subscriptionPlanRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // resolveAndSync — pipeline orchestration
    // -------------------------------------------------------------------------

    public function test_resolve_and_sync_calls_pipeline_in_order(): void
    {
        $this->cartRepository->shouldReceive('findBySessionOrUser')->once()->andReturn(collect());
        $this->collector->shouldReceive('collect')->once()->with(Mockery::type(CartContext::class))->andReturn([]);
        $this->strategy->shouldReceive('resolve')->once()->with([], [])->andReturn([]);
        $this->giftChecklistService->shouldReceive('getGiftsInCart')->once()->andReturn(collect());

        $result = $this->service->resolveAndSync('session-abc', 1);

        $this->assertArrayHasKey('added', $result);
        $this->assertArrayHasKey('updated', $result);
        $this->assertArrayHasKey('removed', $result);
    }

    public function test_resolve_and_sync_passes_cart_context_to_collector(): void
    {
        $this->cartRepository->shouldReceive('findBySessionOrUser')
            ->with(1, 'session-abc')
            ->andReturn(collect());

        $this->collector->shouldReceive('collect')
            ->once()
            ->with(Mockery::on(function (CartContext $ctx) {
                return $ctx->userId === 1 && $ctx->isFirstOrder === true;
            }))
            ->andReturn([]);

        $this->strategy->shouldReceive('resolve')->andReturn([]);
        $this->giftChecklistService->shouldReceive('getGiftsInCart')->andReturn(collect());

        $this->service->resolveAndSync('session-abc', 1, null, isFirstOrder: true);
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // syncCartGifts — add
    // -------------------------------------------------------------------------

    public function test_new_gift_line_is_added_to_cart(): void
    {
        $giftLine = $this->makeGiftLine(productId: 10, quantity: 1);

        $this->cartRepository->shouldReceive('findBySessionOrUser')->andReturn(collect());
        $this->collector->shouldReceive('collect')->andReturn([]);
        $this->strategy->shouldReceive('resolve')->andReturn([$giftLine]);
        $this->productRepository->shouldReceive('findByIds')->andReturn(collect());
        $this->subscriptionPlanRepository->shouldReceive('findByIds')->andReturn(collect());
        $this->giftChecklistService->shouldReceive('getGiftsInCart')->andReturn(collect());

        $this->giftChecklistService->shouldReceive('addGift')
            ->once()
            ->with(Mockery::on(fn($item) => $item->productId === 10));

        $result = $this->service->resolveAndSync('session-abc', 1);

        $this->assertCount(1, $result['added']);
        $this->assertEmpty($result['updated']);
        $this->assertEmpty($result['removed']);
    }

    // -------------------------------------------------------------------------
    // syncCartGifts — remove
    // -------------------------------------------------------------------------

    public function test_stale_gift_is_removed_from_cart(): void
    {
        // Cart has a gift for product 10
        $staleCartGift = $this->makeCartGiftItem(id: 99, productId: 10, quantity: 1);

        $this->cartRepository->shouldReceive('findBySessionOrUser')->andReturn(collect());
        $this->collector->shouldReceive('collect')->andReturn([]);
        $this->strategy->shouldReceive('resolve')->andReturn([]); // nothing desired
        $this->giftChecklistService->shouldReceive('getGiftsInCart')->andReturn(collect([$staleCartGift]));

        $this->giftChecklistService->shouldReceive('removeGift')
            ->once()
            ->with(99);

        $result = $this->service->resolveAndSync('session-abc', 1);

        $this->assertEmpty($result['added']);
        $this->assertEmpty($result['updated']);
        $this->assertContains(99, $result['removed']);
    }

    public function test_qualifying_product_removed_from_cart_removes_gift(): void
    {
        // Previously had product in cart → gift was added.
        // Now product is gone → collector returns no candidates → strategy returns no lines.
        $existingGift = $this->makeCartGiftItem(id: 50, productId: 10, quantity: 1);

        $this->cartRepository->shouldReceive('findBySessionOrUser')->andReturn(collect());
        $this->collector->shouldReceive('collect')->andReturn([]);
        $this->strategy->shouldReceive('resolve')->andReturn([]);
        $this->giftChecklistService->shouldReceive('getGiftsInCart')->andReturn(collect([$existingGift]));

        $this->giftChecklistService->shouldReceive('removeGift')->once()->with(50);

        $this->service->resolveAndSync('session-abc', 1);
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // BUG FIX #5: syncCartGifts — quantity updates
    // -------------------------------------------------------------------------

    public function test_gift_quantity_updated_when_trigger_count_changes(): void
    {
        // Cart has gift for product 10 with quantity 1.
        // Resolver now says quantity should be 3 (customer added more qualifying items).
        $existingGift = $this->makeCartGiftItem(id: 77, productId: 10, quantity: 1);
        $desiredLine = $this->makeGiftLine(productId: 10, quantity: 3);

        $this->cartRepository->shouldReceive('findBySessionOrUser')->andReturn(collect());
        $this->collector->shouldReceive('collect')->andReturn([]);
        $this->strategy->shouldReceive('resolve')->andReturn([$desiredLine]);
        $this->productRepository->shouldReceive('findByIds')->andReturn(collect());
        $this->subscriptionPlanRepository->shouldReceive('findByIds')->andReturn(collect());
        $this->giftChecklistService->shouldReceive('getGiftsInCart')->andReturn(collect([$existingGift]));

        // Should NOT add or remove — only update
        $this->giftChecklistService->shouldNotReceive('addGift');
        $this->giftChecklistService->shouldNotReceive('removeGift');

        $this->cartRepository->shouldReceive('updateQuantity')
            ->once()
            ->with(77, 3);

        $result = $this->service->resolveAndSync('session-abc', 1);

        $this->assertEmpty($result['added']);
        $this->assertArrayHasKey(77, $result['updated']);
        $this->assertEquals(3, $result['updated'][77]);
        $this->assertEmpty($result['removed']);
    }

    public function test_gift_not_updated_when_quantity_unchanged(): void
    {
        $existingGift = $this->makeCartGiftItem(id: 77, productId: 10, quantity: 2);
        $desiredLine = $this->makeGiftLine(productId: 10, quantity: 2); // same quantity

        $this->cartRepository->shouldReceive('findBySessionOrUser')->andReturn(collect());
        $this->collector->shouldReceive('collect')->andReturn([]);
        $this->strategy->shouldReceive('resolve')->andReturn([$desiredLine]);
        $this->productRepository->shouldReceive('findByIds')->andReturn(collect());
        $this->subscriptionPlanRepository->shouldReceive('findByIds')->andReturn(collect());
        $this->giftChecklistService->shouldReceive('getGiftsInCart')->andReturn(collect([$existingGift]));

        $this->cartRepository->shouldNotReceive('updateQuantity');
        $this->giftChecklistService->shouldNotReceive('addGift');
        $this->giftChecklistService->shouldNotReceive('removeGift');

        $result = $this->service->resolveAndSync('session-abc', 1);

        $this->assertEmpty($result['added']);
        $this->assertEmpty($result['updated']);
        $this->assertEmpty($result['removed']);
    }

    public function test_quantity_decreases_when_qualifying_items_partially_removed(): void
    {
        // Had 3 gifts, now only 1 qualifying item remains → quantity drops to 1
        $existingGift = $this->makeCartGiftItem(id: 55, productId: 10, quantity: 3);
        $desiredLine = $this->makeGiftLine(productId: 10, quantity: 1);

        $this->cartRepository->shouldReceive('findBySessionOrUser')->andReturn(collect());
        $this->collector->shouldReceive('collect')->andReturn([]);
        $this->strategy->shouldReceive('resolve')->andReturn([$desiredLine]);
        $this->productRepository->shouldReceive('findByIds')->andReturn(collect());
        $this->subscriptionPlanRepository->shouldReceive('findByIds')->andReturn(collect());
        $this->giftChecklistService->shouldReceive('getGiftsInCart')->andReturn(collect([$existingGift]));

        $this->cartRepository->shouldReceive('updateQuantity')->once()->with(55, 1);

        $result = $this->service->resolveAndSync('session-abc', 1);

        $this->assertArrayHasKey(55, $result['updated']);
        $this->assertEquals(1, $result['updated'][55]);
    }

    // -------------------------------------------------------------------------
    // preview — no cart mutation
    // -------------------------------------------------------------------------

    public function test_preview_does_not_mutate_cart(): void
    {
        $giftLine = $this->makeGiftLine(productId: 10, quantity: 1);

        $this->cartRepository->shouldReceive('findBySessionOrUser')->andReturn(collect());
        $this->collector->shouldReceive('collect')->andReturn([]);
        $this->strategy->shouldReceive('resolve')->andReturn([$giftLine]);
        $this->productRepository->shouldReceive('findByIds')->andReturn(collect());
        $this->subscriptionPlanRepository->shouldReceive('findByIds')->andReturn(collect());

        // GiftChecklistService must never be called during preview
        $this->giftChecklistService->shouldNotReceive('getGiftsInCart');
        $this->giftChecklistService->shouldNotReceive('addGift');
        $this->giftChecklistService->shouldNotReceive('removeGift');
        $this->cartRepository->shouldNotReceive('updateQuantity');

        $lines = $this->service->preview('session-abc', 1);

        $this->assertCount(1, $lines);
        $this->assertEquals(10, $lines[0]->giftProductId);
    }

    // -------------------------------------------------------------------------
    // Label hydration
    // -------------------------------------------------------------------------

    public function test_product_gift_label_fetched_from_product_repository(): void
    {
        $candidate = new PromotionCandidate(
            promotionId: 1,
            merchantId: null,
            giftType: GiftType::PRODUCT,
            giftProductId: 42,
            giftSubscriptionPlanId: null,
            quantityRule: GiftQuantityRule::ONE_PER_QUALIFYING,
            maxPerOrder: 1,
            exclusive: false,
            priority: 0,
            triggerCount: 1,
        );

        $mockProduct = (object)['id' => 42, 'name' => 'Free Mug'];
        $giftLine = $this->makeGiftLine(productId: 42, quantity: 1);

        $this->cartRepository->shouldReceive('findBySessionOrUser')->andReturn(collect());
        $this->collector->shouldReceive('collect')->andReturn([$candidate]);

        $this->productRepository->shouldReceive('findMany')
            ->once()
            ->with([42])
            ->andReturn(collect([$mockProduct]));

        $this->subscriptionPlanRepository->shouldReceive('findByIds')
            ->with([])
            ->andReturn(collect());

        $this->strategy->shouldReceive('resolve')
            ->once()
            ->with([$candidate], ['product:42' => 'Free Mug'])
            ->andReturn([$giftLine]);

        $this->giftChecklistService->shouldReceive('getGiftsInCart')->andReturn(collect());
        $this->giftChecklistService->shouldReceive('addGift')->once();

        $this->service->resolveAndSync('session-abc', 1);
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Cart context construction
    // -------------------------------------------------------------------------

    public function test_gift_items_in_cart_are_not_counted_in_cart_total(): void
    {
        $giftItem = (object)[
            'id' => 1,
            'product_id' => 99,
            'subscription_plan_id' => null,
            'price' => 25.0,
            'quantity' => 1,
            'merchant_id' => null,
            'category_ids' => null,
            'options' => json_encode(['type' => CartItemType::FREE_GIFT->value]),
        ];

        $regularItem = (object)[
            'id' => 2,
            'product_id' => 10,
            'subscription_plan_id' => null,
            'price' => 50.0,
            'quantity' => 1,
            'merchant_id' => null,
            'category_ids' => null,
            'options' => json_encode(['type' => CartItemType::PRODUCT->value]),
        ];

        $this->cartRepository->shouldReceive('findBySessionOrUser')
            ->andReturn(collect([$giftItem, $regularItem]));

        $this->collector->shouldReceive('collect')
            ->once()
            ->with(Mockery::on(function (CartContext $ctx) {
                // Only the regular item's price (50.0) should be in cartTotal
                return $ctx->cartTotal === 50.0 && $ctx->itemCount === 1;
            }))
            ->andReturn([]);

        $this->strategy->shouldReceive('resolve')->andReturn([]);
        $this->giftChecklistService->shouldReceive('getGiftsInCart')->andReturn(collect());

        $this->service->resolveAndSync('session-abc', 1);
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Factories
    // -------------------------------------------------------------------------

    private function makeGiftLine(
        ?int   $productId = null,
        ?int   $subscriptionPlanId = null,
        int    $quantity = 1,
        int    $promotionId = 1,
        string $label = 'Free Gift',
    ): GiftLine
    {
        $giftType = $subscriptionPlanId !== null ? GiftType::SUBSCRIPTION : GiftType::PRODUCT;

        return new GiftLine(
            giftType: $giftType,
            giftProductId: $productId,
            giftSubscriptionPlanId: $subscriptionPlanId,
            quantity: $quantity,
            sourcePromotionId: $promotionId,
            label: $label,
        );
    }

    private function makeCartGiftItem(int $id, ?int $productId = null, ?int $subscriptionPlanId = null, int $quantity = 1): object
    {
        $options = ['type' => CartItemType::FREE_GIFT->value];

        if ($productId !== null) {
            $options['product_id'] = $productId;
        }

        if ($subscriptionPlanId !== null) {
            $options['subscription_plan_id'] = $subscriptionPlanId;
        }

        return (object)[
            'id' => $id,
            'quantity' => $quantity,
            'options' => json_encode($options),
        ];
    }
}