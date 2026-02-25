<?php

namespace App\Tests\Unit\Services\Shopping\Gift;

use App\DTO\Cart\CartContext;
use App\DTO\Cart\CartLineItem;
use App\Enums\CartItemType;
use App\Enums\Gifts\GiftQuantityRule;
use App\Enums\Gifts\GiftTriggerOperator;
use App\Enums\Gifts\GiftTriggerType;
use App\Enums\Gifts\GiftType;
use App\Repositories\Shopping\GiftPromotionRepository;
use App\Services\Shopping\Resolvers\GiftEligibilityCollector;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class GiftEligibilityCollectorTest extends TestCase
{
    private GiftPromotionRepository|MockInterface $repository;
    private GiftEligibilityCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(GiftPromotionRepository::class);
        $this->collector = new GiftEligibilityCollector($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Empty / no-match cases
    // -------------------------------------------------------------------------

    public function test_returns_empty_when_repository_returns_no_promotions(): void
    {
        $this->repository
            ->shouldReceive('findCandidatesForCart')
            ->once()
            ->andReturn(collect());

        $result = $this->collector->collect($this->makeCart());

        $this->assertEmpty($result);
    }

    public function test_returns_empty_when_promotion_has_no_triggers(): void
    {
        $promotion = $this->makePromotion(1);

        $this->repositoryReturns([$promotion], [1 => collect()]);

        $result = $this->collector->collect($this->makeCart(productIds: [42]));

        $this->assertEmpty($result);
    }

    // -------------------------------------------------------------------------
    // Entity trigger — product
    // -------------------------------------------------------------------------

    public function test_product_equals_trigger_matches_cart_item(): void
    {
        $promotion = $this->makePromotion(1);
        $trigger = $this->makeTrigger(1, GiftTriggerType::PRODUCT, GiftTriggerOperator::EQUALS, referenceId: 42);

        $this->repositoryReturns([$promotion], [1 => collect([$trigger])]);

        $candidates = $this->collector->collect($this->makeCart(productIds: [42]));

        $this->assertCount(1, $candidates);
        $this->assertEquals(1, $candidates[0]->promotionId);
    }

    public function test_product_equals_trigger_does_not_match_different_product(): void
    {
        $promotion = $this->makePromotion(1);
        $trigger = $this->makeTrigger(1, GiftTriggerType::PRODUCT, GiftTriggerOperator::EQUALS, referenceId: 42);

        $this->repositoryReturns([$promotion], [1 => collect([$trigger])]);

        $candidates = $this->collector->collect($this->makeCart(productIds: [99]));

        $this->assertEmpty($candidates);
    }

    public function test_product_in_trigger_matches_any_of_set(): void
    {
        $promotion = $this->makePromotion(1);
        $trigger = $this->makeTrigger(1, GiftTriggerType::PRODUCT, GiftTriggerOperator::IN, valueSet: [10, 20, 30]);

        $this->repositoryReturns([$promotion], [1 => collect([$trigger])]);

        $candidates = $this->collector->collect($this->makeCart(productIds: [20]));

        $this->assertCount(1, $candidates);
    }

    public function test_product_in_trigger_fails_when_none_match(): void
    {
        $promotion = $this->makePromotion(1);
        $trigger = $this->makeTrigger(1, GiftTriggerType::PRODUCT, GiftTriggerOperator::IN, valueSet: [10, 20, 30]);

        $this->repositoryReturns([$promotion], [1 => collect([$trigger])]);

        $candidates = $this->collector->collect($this->makeCart(productIds: [99]));

        $this->assertEmpty($candidates);
    }

    // -------------------------------------------------------------------------
    // Entity trigger — subscription plan
    // -------------------------------------------------------------------------

    public function test_subscription_plan_trigger_matches_cart_plan(): void
    {
        $promotion = $this->makePromotion(1);
        $trigger = $this->makeTrigger(1, GiftTriggerType::SUBSCRIPTION_PLAN, GiftTriggerOperator::EQUALS, referenceId: 7);

        $this->repositoryReturns([$promotion], [1 => collect([$trigger])]);

        $candidates = $this->collector->collect($this->makeCart(subscriptionPlanIds: [7]));

        $this->assertCount(1, $candidates);
    }

    // -------------------------------------------------------------------------
    // Gate triggers — cart_total, item_count
    // -------------------------------------------------------------------------

    public function test_cart_total_gte_passes_when_threshold_met(): void
    {
        $promotion = $this->makePromotion(1);
        $trigger = $this->makeTrigger(1, GiftTriggerType::CART_TOTAL, GiftTriggerOperator::GREATER_THAN_OR_EQUAL, value: 50.0);

        $this->repositoryReturns([$promotion], [1 => collect([$trigger])]);

        $candidates = $this->collector->collect($this->makeCart(cartTotal: 75.0));

        $this->assertCount(1, $candidates);
    }

    public function test_cart_total_gte_fails_when_below_threshold(): void
    {
        $promotion = $this->makePromotion(1);
        $trigger = $this->makeTrigger(1, GiftTriggerType::CART_TOTAL, GiftTriggerOperator::GREATER_THAN_OR_EQUAL, value: 50.0);

        $this->repositoryReturns([$promotion], [1 => collect([$trigger])]);

        $candidates = $this->collector->collect($this->makeCart(cartTotal: 49.99));

        $this->assertEmpty($candidates);
    }

    public function test_item_count_trigger_passes_when_threshold_met(): void
    {
        $promotion = $this->makePromotion(1);
        $trigger = $this->makeTrigger(1, GiftTriggerType::ITEM_COUNT, GiftTriggerOperator::GREATER_THAN_OR_EQUAL, value: 3.0);

        $this->repositoryReturns([$promotion], [1 => collect([$trigger])]);

        $candidates = $this->collector->collect($this->makeCart(itemCount: 5));

        $this->assertCount(1, $candidates);
    }

    // -------------------------------------------------------------------------
    // Gate trigger — first_time_buyer
    // -------------------------------------------------------------------------

    public function test_first_time_buyer_passes_for_first_order(): void
    {
        $promotion = $this->makePromotion(1);
        $trigger = $this->makeTrigger(1, GiftTriggerType::FIRST_TIME_BUYER, GiftTriggerOperator::EQUALS);

        $this->repositoryReturns([$promotion], [1 => collect([$trigger])]);

        $candidates = $this->collector->collect($this->makeCart(isFirstOrder: true));

        $this->assertCount(1, $candidates);
    }

    public function test_first_time_buyer_fails_for_repeat_buyer(): void
    {
        $promotion = $this->makePromotion(1);
        $trigger = $this->makeTrigger(1, GiftTriggerType::FIRST_TIME_BUYER, GiftTriggerOperator::EQUALS);

        $this->repositoryReturns([$promotion], [1 => collect([$trigger])]);

        $candidates = $this->collector->collect($this->makeCart(isFirstOrder: false));

        $this->assertEmpty($candidates);
    }

    // -------------------------------------------------------------------------
    // AND logic — same group_key
    // -------------------------------------------------------------------------

    public function test_and_group_fails_when_one_trigger_fails(): void
    {
        $promotion = $this->makePromotion(1);
        $triggerA = $this->makeTrigger(1, GiftTriggerType::PRODUCT, GiftTriggerOperator::EQUALS, referenceId: 5, groupKey: 'A');
        $triggerB = $this->makeTrigger(1, GiftTriggerType::CART_TOTAL, GiftTriggerOperator::GREATER_THAN_OR_EQUAL, value: 100.0, groupKey: 'A');

        $this->repositoryReturns([$promotion], [1 => collect([$triggerA, $triggerB])]);

        // Product matches but cart total does not
        $cart = $this->makeCartWithLines(
            lines: [$this->makeLineItem(productId: 5, quantity: 1, price: 30.0)],
            cartTotal: 30.0,
        );

        $candidates = $this->collector->collect($cart);

        $this->assertEmpty($candidates);
    }

    public function test_and_group_passes_when_all_triggers_pass(): void
    {
        $promotion = $this->makePromotion(1);
        $triggerA = $this->makeTrigger(1, GiftTriggerType::PRODUCT, GiftTriggerOperator::EQUALS, referenceId: 5, groupKey: 'A');
        $triggerB = $this->makeTrigger(1, GiftTriggerType::CART_TOTAL, GiftTriggerOperator::GREATER_THAN_OR_EQUAL, value: 50.0, groupKey: 'A');

        $this->repositoryReturns([$promotion], [1 => collect([$triggerA, $triggerB])]);

        $cart = $this->makeCartWithLines(
            lines: [$this->makeLineItem(productId: 5, quantity: 1, price: 75.0)],
            cartTotal: 75.0,
        );

        $candidates = $this->collector->collect($cart);

        $this->assertCount(1, $candidates);
    }

    // -------------------------------------------------------------------------
    // BUG FIX #1: AND group triggerCount — min() not max(), gates excluded
    // -------------------------------------------------------------------------

    public function test_and_group_uses_min_count_not_max_across_entity_triggers(): void
    {
        // Product A AND Product B in same group.
        // 3x Product A, 1x Product B → should fire ONCE (limited by Product B).
        $promotion = $this->makePromotion(1);
        $triggerA = $this->makeTrigger(1, GiftTriggerType::PRODUCT, GiftTriggerOperator::EQUALS, referenceId: 10, groupKey: 'A');
        $triggerB = $this->makeTrigger(1, GiftTriggerType::PRODUCT, GiftTriggerOperator::EQUALS, referenceId: 20, groupKey: 'A');

        $this->repositoryReturns([$promotion], [1 => collect([$triggerA, $triggerB])]);

        $cart = $this->makeCartWithLines([
            $this->makeLineItem(productId: 10, quantity: 3),
            $this->makeLineItem(productId: 20, quantity: 1),
        ]);

        $candidates = $this->collector->collect($cart);

        $this->assertCount(1, $candidates);
        $this->assertEquals(1, $candidates[0]->triggerCount,
            'triggerCount must be min(3, 1) = 1, not max(3, 1) = 3'
        );
    }

    public function test_gate_trigger_does_not_poison_and_group_count(): void
    {
        // Product A (count=2) AND cart_total >= 50 (gate, count=0).
        // Without the fix, min(2, 0) = 0 would mark the group as ineligible.
        // With the fix, gate triggers are excluded from min() and count = 2.
        $promotion = $this->makePromotion(1);
        $triggerA = $this->makeTrigger(1, GiftTriggerType::PRODUCT, GiftTriggerOperator::EQUALS, referenceId: 5, groupKey: 'A');
        $triggerB = $this->makeTrigger(1, GiftTriggerType::CART_TOTAL, GiftTriggerOperator::GREATER_THAN_OR_EQUAL, value: 50.0, groupKey: 'A');

        $this->repositoryReturns([$promotion], [1 => collect([$triggerA, $triggerB])]);

        $cart = $this->makeCartWithLines(
            lines: [$this->makeLineItem(productId: 5, quantity: 2, price: 40.0)],
            cartTotal: 80.0,
        );

        $candidates = $this->collector->collect($cart);

        $this->assertCount(1, $candidates);
        $this->assertEquals(2, $candidates[0]->triggerCount,
            'Gate trigger (cart_total) must not reduce entity trigger count via min()'
        );
    }

    public function test_group_with_only_gate_triggers_defaults_trigger_count_to_one(): void
    {
        // cart_total only — no entity triggers. Should be eligible with count = 1.
        $promotion = $this->makePromotion(1);
        $trigger = $this->makeTrigger(1, GiftTriggerType::CART_TOTAL, GiftTriggerOperator::GREATER_THAN_OR_EQUAL, value: 50.0);

        $this->repositoryReturns([$promotion], [1 => collect([$trigger])]);

        $candidates = $this->collector->collect($this->makeCart(cartTotal: 100.0));

        $this->assertCount(1, $candidates);
        $this->assertEquals(1, $candidates[0]->triggerCount);
    }

    public function test_three_entity_triggers_in_and_group_uses_minimum(): void
    {
        $promotion = $this->makePromotion(1);
        $triggerA = $this->makeTrigger(1, GiftTriggerType::PRODUCT, GiftTriggerOperator::EQUALS, referenceId: 1, groupKey: 'A');
        $triggerB = $this->makeTrigger(1, GiftTriggerType::PRODUCT, GiftTriggerOperator::EQUALS, referenceId: 2, groupKey: 'A');
        $triggerC = $this->makeTrigger(1, GiftTriggerType::PRODUCT, GiftTriggerOperator::EQUALS, referenceId: 3, groupKey: 'A');

        $this->repositoryReturns([$promotion], [1 => collect([$triggerA, $triggerB, $triggerC])]);

        $cart = $this->makeCartWithLines([
            $this->makeLineItem(productId: 1, quantity: 5),
            $this->makeLineItem(productId: 2, quantity: 3),
            $this->makeLineItem(productId: 3, quantity: 7),
        ]);

        $candidates = $this->collector->collect($cart);

        $this->assertCount(1, $candidates);
        $this->assertEquals(3, $candidates[0]->triggerCount,
            'triggerCount must be min(5, 3, 7) = 3'
        );
    }

    // -------------------------------------------------------------------------
    // OR logic — different group_keys
    // -------------------------------------------------------------------------

    public function test_promotion_is_eligible_when_any_group_passes(): void
    {
        $promotion = $this->makePromotion(1);
        $triggerA = $this->makeTrigger(1, GiftTriggerType::PRODUCT, GiftTriggerOperator::EQUALS, referenceId: 5, groupKey: 'A');
        $triggerB = $this->makeTrigger(1, GiftTriggerType::SUBSCRIPTION_PLAN, GiftTriggerOperator::EQUALS, referenceId: 9, groupKey: 'B');

        $this->repositoryReturns([$promotion], [1 => collect([$triggerA, $triggerB])]);

        // Only subscription plan group (B) passes
        $candidates = $this->collector->collect($this->makeCart(subscriptionPlanIds: [9]));

        $this->assertCount(1, $candidates);
    }

    public function test_promotion_is_ineligible_when_all_groups_fail(): void
    {
        $promotion = $this->makePromotion(1);
        $triggerA = $this->makeTrigger(1, GiftTriggerType::PRODUCT, GiftTriggerOperator::EQUALS, referenceId: 5, groupKey: 'A');
        $triggerB = $this->makeTrigger(1, GiftTriggerType::SUBSCRIPTION_PLAN, GiftTriggerOperator::EQUALS, referenceId: 9, groupKey: 'B');

        $this->repositoryReturns([$promotion], [1 => collect([$triggerA, $triggerB])]);

        $candidates = $this->collector->collect($this->makeCart(productIds: [99], subscriptionPlanIds: [99]));

        $this->assertEmpty($candidates);
    }

    public function test_or_uses_highest_trigger_count_across_passing_groups(): void
    {
        // Group A: 2x Product 10 → count = 2
        // Group B: 1x Product 20 → count = 1
        // Both pass. Highest count wins (2) so customer gets more gifts.
        $promotion = $this->makePromotion(1);
        $triggerA = $this->makeTrigger(1, GiftTriggerType::PRODUCT, GiftTriggerOperator::EQUALS, referenceId: 10, groupKey: 'A');
        $triggerB = $this->makeTrigger(1, GiftTriggerType::PRODUCT, GiftTriggerOperator::EQUALS, referenceId: 20, groupKey: 'B');

        $this->repositoryReturns([$promotion], [1 => collect([$triggerA, $triggerB])]);

        $cart = $this->makeCartWithLines([
            $this->makeLineItem(productId: 10, quantity: 2),
            $this->makeLineItem(productId: 20, quantity: 1),
        ]);

        $candidates = $this->collector->collect($cart);

        $this->assertCount(1, $candidates);
        $this->assertEquals(2, $candidates[0]->triggerCount);
    }

    // -------------------------------------------------------------------------
    // Negation
    // -------------------------------------------------------------------------

    public function test_negated_trigger_passes_when_condition_not_met(): void
    {
        $promotion = $this->makePromotion(1);
        $trigger = $this->makeTrigger(
            1,
            GiftTriggerType::FIRST_TIME_BUYER,
            GiftTriggerOperator::EQUALS,
            negated: true
        );

        $this->repositoryReturns([$promotion], [1 => collect([$trigger])]);

        // Repeat buyer + negated = eligible
        $candidates = $this->collector->collect($this->makeCart(isFirstOrder: false));

        $this->assertCount(1, $candidates);
    }

    public function test_negated_trigger_fails_when_condition_is_met(): void
    {
        $promotion = $this->makePromotion(1);
        $trigger = $this->makeTrigger(1, GiftTriggerType::FIRST_TIME_BUYER, GiftTriggerOperator::EQUALS, negated: true);

        $this->repositoryReturns([$promotion], [1 => collect([$trigger])]);

        $candidates = $this->collector->collect($this->makeCart(isFirstOrder: true));

        $this->assertEmpty($candidates);
    }

    public function test_negated_product_trigger_blocks_promotion_when_product_in_cart(): void
    {
        $promotion = $this->makePromotion(1);
        $trigger = $this->makeTrigger(
            1,
            GiftTriggerType::PRODUCT,
            GiftTriggerOperator::EQUALS,
            referenceId: 5,
            negated: true
        );

        $this->repositoryReturns([$promotion], [1 => collect([$trigger])]);

        $candidates = $this->collector->collect($this->makeCart(productIds: [5]));

        $this->assertEmpty($candidates);
    }

    public function test_negated_product_trigger_passes_when_product_absent(): void
    {
        $promotion = $this->makePromotion(1);
        $trigger = $this->makeTrigger(
            1,
            GiftTriggerType::PRODUCT,
            GiftTriggerOperator::EQUALS,
            referenceId: 5,
            negated: true
        );

        $this->repositoryReturns([$promotion], [1 => collect([$trigger])]);

        $candidates = $this->collector->collect($this->makeCart(productIds: [99]));

        $this->assertCount(1, $candidates);
    }

    // -------------------------------------------------------------------------
    // Gift item exclusion (anti-recursion)
    // -------------------------------------------------------------------------

    public function test_gift_items_are_excluded_from_trigger_evaluation(): void
    {
        $promotion = $this->makePromotion(1);
        $trigger = $this->makeTrigger(1, GiftTriggerType::PRODUCT, GiftTriggerOperator::EQUALS, referenceId: 42);

        $this->repositoryReturns([$promotion], [1 => collect([$trigger])]);

        // Product 42 is in cart but as a gift — must not trigger more gifts
        $cart = $this->makeCartWithLines([
            $this->makeLineItem(productId: 42, quantity: 2, isGift: true),
        ]);

        $candidates = $this->collector->collect($cart);

        $this->assertEmpty($candidates);
    }

    public function test_mix_of_gift_and_regular_items_only_counts_regular(): void
    {
        $promotion = $this->makePromotion(1);
        $trigger = $this->makeTrigger(1, GiftTriggerType::PRODUCT, GiftTriggerOperator::EQUALS, referenceId: 42);

        $this->repositoryReturns([$promotion], [1 => collect([$trigger])]);

        $cart = $this->makeCartWithLines([
            $this->makeLineItem(productId: 42, quantity: 3, isGift: false),
            $this->makeLineItem(productId: 42, quantity: 5, isGift: true),  // should be ignored
        ]);

        $candidates = $this->collector->collect($cart);

        $this->assertCount(1, $candidates);
        $this->assertEquals(3, $candidates[0]->triggerCount,
            'Gift items must not contribute to trigger count'
        );
    }

    // -------------------------------------------------------------------------
    // Multiple promotions
    // -------------------------------------------------------------------------

    public function test_multiple_promotions_each_evaluated_independently(): void
    {
        $promoA = $this->makePromotion(1);
        $promoB = $this->makePromotion(2);
        $triggerA = $this->makeTrigger(1, GiftTriggerType::PRODUCT, GiftTriggerOperator::EQUALS, referenceId: 10);
        $triggerB = $this->makeTrigger(2, GiftTriggerType::PRODUCT, GiftTriggerOperator::EQUALS, referenceId: 20);

        $this->repositoryReturns(
            [$promoA, $promoB],
            [
                1 => collect([$triggerA]),
                2 => collect([$triggerB]),
            ]
        );

        // Only product 10 in cart → only promo A eligible
        $candidates = $this->collector->collect($this->makeCart(productIds: [10]));

        $this->assertCount(1, $candidates);
        $this->assertEquals(1, $candidates[0]->promotionId);
    }

    // -------------------------------------------------------------------------
    // Factories and helpers
    // -------------------------------------------------------------------------

    private function makePromotion(int $id, ?int $merchantId = null): object
    {
        return (object)[
            'id' => $id,
            'merchantId' => $merchantId,
            'giftType' => GiftType::PRODUCT,
            'giftProductId' => 100 + $id,
            'giftSubscriptionPlanId' => null,
            'quantityRule' => GiftQuantityRule::ONE_PER_QUALIFYING,
            'maxPerOrder' => 10,
            'exclusive' => false,
            'priority' => 0,
        ];
    }

    private function makeTrigger(
        int                 $promotionId,
        GiftTriggerType     $type,
        GiftTriggerOperator $operator,
        ?int                $referenceId = null,
        ?float              $value = null,
        ?array              $valueSet = null,
        string              $groupKey = 'A',
        bool                $negated = false,
    ): object
    {
        return (object)[
            'id' => random_int(1, 99999),
            'promotionId' => $promotionId,
            'type' => $type,
            'operator' => $operator,
            'referenceId' => $referenceId,
            'value' => $value,
            'valueSet' => $valueSet,
            'groupKey' => $groupKey,
            'negated' => $negated,
        ];
    }

    private function makeCart(
        array $productIds = [],
        array $subscriptionPlanIds = [],
        float $cartTotal = 0.0,
        int   $itemCount = 0,
        bool  $isFirstOrder = false,
    ): CartContext
    {
        $lines = [];

        foreach ($productIds as $id) {
            $lines[] = $this->makeLineItem(productId: $id);
        }

        foreach ($subscriptionPlanIds as $id) {
            $lines[] = $this->makeLineItem(subscriptionPlanId: $id);
        }

        return new CartContext(
            lineItems: $lines,
            cartTotal: $cartTotal,
            itemCount: $itemCount ?: count($lines),
            isFirstOrder: $isFirstOrder,
            userId: null,
            merchantId: null,
        );
    }

    private function makeCartWithLines(
        array  $lines,
        ?float $cartTotal = null,
        bool   $isFirstOrder = false,
    ): CartContext
    {
        $total = $cartTotal ?? array_sum(
            array_map(fn($l) => $l->isGift ? 0.0 : $l->price * $l->quantity, $lines)
        );
        $count = array_sum(array_map(fn($l) => $l->isGift ? 0 : $l->quantity, $lines));

        return new CartContext(
            lineItems: $lines,
            cartTotal: $total,
            itemCount: $count,
            isFirstOrder: $isFirstOrder,
            userId: null,
            merchantId: null,
        );
    }

    private function makeLineItem(
        ?int  $productId = null,
        ?int  $subscriptionPlanId = null,
        int   $quantity = 1,
        float $price = 10.0,
        bool  $isGift = false,
        array $categoryIds = [],
    ): CartLineItem
    {
        return new CartLineItem(
            cartItemId: random_int(1, 99999),
            type: $isGift ? CartItemType::FREE_GIFT : CartItemType::PRODUCT,
            productId: $productId,
            subscriptionPlanId: $subscriptionPlanId,
            price: $price,
            quantity: $quantity,
            isGift: $isGift,
            merchantId: null,
            categoryIds: $categoryIds,
        );
    }

    private function repositoryReturns(array $promotions, array $triggersByPromotion): void
    {
        $this->repository
            ->shouldReceive('findCandidatesForCart')
            ->once()
            ->andReturn(collect($promotions));

        $this->repository
            ->shouldReceive('findTriggersForPromotions')
            ->once()
            ->with(array_map(fn($p) => $p->id, $promotions))
            ->andReturn(collect($triggersByPromotion));
    }
}