<?php

namespace App\Tests\Unit\Services\Shopping\Gift;

use App\DTO\Cart\PromotionCandidate;
use App\Enums\Gifts\GiftQuantityRule;
use App\Enums\Gifts\GiftType;
use App\Services\Shopping\Resolvers\GiftResolutionStrategy;
use Mockery;
use PHPUnit\Framework\TestCase;

class GiftResolutionStrategyTest extends TestCase
{
    private GiftResolutionStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();

        // Strategy is pure logic with no dependencies — no mocks needed here.
        // Mockery is imported for consistency with the test suite style.
        $this->strategy = new GiftResolutionStrategy();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Empty / base cases
    // -------------------------------------------------------------------------

    public function test_returns_empty_when_no_candidates(): void
    {
        $this->assertEmpty($this->strategy->resolve([]));
    }

    public function test_single_candidate_produces_one_gift_line(): void
    {
        $candidate = $this->makeCandidate(promotionId: 1, giftProductId: 10, triggerCount: 1);

        $lines = $this->strategy->resolve([$candidate], ['product:10' => 'Free Mug']);

        $this->assertCount(1, $lines);
        $this->assertEquals(10, $lines[0]->giftProductId);
        $this->assertEquals(1, $lines[0]->quantity);
        $this->assertEquals('Free Mug', $lines[0]->label);
        $this->assertEquals(1, $lines[0]->sourcePromotionId);
    }

    // -------------------------------------------------------------------------
    // ONE_PER_QUALIFYING quantity rule
    // -------------------------------------------------------------------------

    public function test_one_per_qualifying_quantity_equals_trigger_count(): void
    {
        $candidate = $this->makeCandidate(
            promotionId: 1,
            giftProductId: 10,
            triggerCount: 3,
            quantityRule: GiftQuantityRule::ONE_PER_QUALIFYING,
            maxPerOrder: 10,
        );

        $lines = $this->strategy->resolve([$candidate]);

        $this->assertEquals(3, $lines[0]->quantity);
    }

    public function test_one_per_qualifying_capped_by_max_per_order(): void
    {
        $candidate = $this->makeCandidate(
            promotionId: 1,
            giftProductId: 10,
            triggerCount: 5,
            quantityRule: GiftQuantityRule::ONE_PER_QUALIFYING,
            maxPerOrder: 2,
        );

        $lines = $this->strategy->resolve([$candidate]);

        $this->assertEquals(2, $lines[0]->quantity);
    }

    // -------------------------------------------------------------------------
    // CAP quantity rule
    // -------------------------------------------------------------------------

    public function test_cap_always_returns_max_per_order_ignoring_trigger_count(): void
    {
        $candidate = $this->makeCandidate(
            promotionId: 1,
            giftProductId: 10,
            triggerCount: 100,
            quantityRule: GiftQuantityRule::CAP,
            maxPerOrder: 1,
        );

        $lines = $this->strategy->resolve([$candidate]);

        $this->assertEquals(1, $lines[0]->quantity);
    }

    public function test_cap_respects_max_per_order_when_trigger_count_is_low(): void
    {
        $candidate = $this->makeCandidate(
            promotionId: 1,
            giftProductId: 10,
            triggerCount: 1,
            quantityRule: GiftQuantityRule::CAP,
            maxPerOrder: 5,
        );

        $lines = $this->strategy->resolve([$candidate]);

        $this->assertEquals(5, $lines[0]->quantity);
    }

    // -------------------------------------------------------------------------
    // BUG FIX #3: MERGE — additive cap, not identical to ONE_PER_QUALIFYING
    // -------------------------------------------------------------------------

    public function test_merge_contributes_max_per_order_to_pool_ignoring_trigger_count(): void
    {
        // MERGE ignores triggerCount. It contributes maxPerOrder to the pool.
        $candidate = $this->makeCandidate(
            promotionId: 1,
            giftProductId: 10,
            triggerCount: 1,      // would give 1 under ONE_PER_QUALIFYING
            quantityRule: GiftQuantityRule::MERGE,
            maxPerOrder: 3,      // MERGE always contributes 3
        );

        $lines = $this->strategy->resolve([$candidate]);

        $this->assertEquals(3, $lines[0]->quantity,
            'MERGE must contribute maxPerOrder (3) to pool, not triggerCount (1)'
        );
    }

    public function test_two_merge_promotions_for_same_gift_sum_their_max_per_orders(): void
    {
        // MERGE is additive: 3 + 3 = 6, not capped at 3.
        $candidateA = $this->makeCandidate(
            promotionId: 1,
            giftProductId: 10,
            triggerCount: 1,
            quantityRule: GiftQuantityRule::MERGE,
            maxPerOrder: 3,
        );
        $candidateB = $this->makeCandidate(
            promotionId: 2,
            giftProductId: 10,
            triggerCount: 1,
            quantityRule: GiftQuantityRule::MERGE,
            maxPerOrder: 3,
        );

        $lines = $this->strategy->resolve([$candidateA, $candidateB]);

        $this->assertCount(1, $lines);
        $this->assertEquals(6, $lines[0]->quantity,
            'Two MERGE promotions (max 3 each) targeting same gift must pool to 6'
        );
    }

    public function test_merge_and_one_per_qualifying_for_same_gift_combine_independently(): void
    {
        // ONE_PER_QUALIFYING: triggerCount=2, maxPerOrder=5 → contributes 2
        // MERGE: maxPerOrder=3 → contributes 3
        // Total: 2 + 3 = 5
        $opqCandidate = $this->makeCandidate(
            promotionId: 1,
            giftProductId: 10,
            triggerCount: 2,
            quantityRule: GiftQuantityRule::ONE_PER_QUALIFYING,
            maxPerOrder: 5,
        );
        $mergeCandidate = $this->makeCandidate(
            promotionId: 2,
            giftProductId: 10,
            triggerCount: 1,
            quantityRule: GiftQuantityRule::MERGE,
            maxPerOrder: 3,
        );

        $lines = $this->strategy->resolve([$opqCandidate, $mergeCandidate]);

        $this->assertCount(1, $lines);
        $this->assertEquals(5, $lines[0]->quantity);
    }

    public function test_merge_is_semantically_distinct_from_one_per_qualifying(): void
    {
        // Key distinction: MERGE ignores triggerCount entirely.
        // Given triggerCount=1, maxPerOrder=5:
        //   ONE_PER_QUALIFYING → min(1, 5) = 1
        //   MERGE              → 5 (always contributes maxPerOrder)
        $opq = $this->makeCandidate(1, giftProductId: 10, triggerCount: 1, quantityRule: GiftQuantityRule::ONE_PER_QUALIFYING, maxPerOrder: 5);
        $mrg = $this->makeCandidate(2, giftProductId: 20, triggerCount: 1, quantityRule: GiftQuantityRule::MERGE, maxPerOrder: 5);

        $opqLines = $this->strategy->resolve([$opq]);
        $mrgLines = $this->strategy->resolve([$mrg]);

        $this->assertEquals(1, $opqLines[0]->quantity, 'ONE_PER_QUALIFYING: min(triggerCount=1, max=5) = 1');
        $this->assertEquals(5, $mrgLines[0]->quantity, 'MERGE: always contributes maxPerOrder=5');
    }

    // -------------------------------------------------------------------------
    // Merging identical gift targets
    // -------------------------------------------------------------------------

    public function test_two_one_per_qualifying_promotions_for_same_gift_merge_quantities(): void
    {
        $candidateA = $this->makeCandidate(promotionId: 1, giftProductId: 10, triggerCount: 1, priority: 10);
        $candidateB = $this->makeCandidate(promotionId: 2, giftProductId: 10, triggerCount: 1, priority: 5);

        $lines = $this->strategy->resolve([$candidateA, $candidateB]);

        $this->assertCount(1, $lines);
        $this->assertEquals(2, $lines[0]->quantity);
        $this->assertEquals(1, $lines[0]->sourcePromotionId,
            'sourcePromotionId should be from highest-priority promotion'
        );
    }

    public function test_merged_regular_promos_capped_by_highest_max_per_order(): void
    {
        // min(3, 2) = 2 from A, min(3, 4) = 3 from B → total = 5, cap = max(2,4) = 4
        $candidateA = $this->makeCandidate(1, giftProductId: 10, triggerCount: 3, maxPerOrder: 2, quantityRule: GiftQuantityRule::ONE_PER_QUALIFYING);
        $candidateB = $this->makeCandidate(2, giftProductId: 10, triggerCount: 3, maxPerOrder: 4, quantityRule: GiftQuantityRule::ONE_PER_QUALIFYING);

        $lines = $this->strategy->resolve([$candidateA, $candidateB]);

        $this->assertCount(1, $lines);
        $this->assertEquals(4, $lines[0]->quantity,
            '2 + 3 = 5, but cap = max(2, 4) = 4'
        );
    }

    public function test_different_gift_targets_produce_separate_lines(): void
    {
        $candidateA = $this->makeCandidate(1, giftProductId: 10);
        $candidateB = $this->makeCandidate(2, giftProductId: 20);

        $lines = $this->strategy->resolve([$candidateA, $candidateB]);

        $this->assertCount(2, $lines);
    }

    public function test_product_and_subscription_gifts_are_separate_lines(): void
    {
        $productGift = $this->makeCandidate(1, giftProductId: 10);
        $subGift = $this->makeCandidate(2, giftSubscriptionPlanId: 5);

        $lines = $this->strategy->resolve([$productGift, $subGift]);

        $this->assertCount(2, $lines);

        $types = array_map(fn($l) => $l->giftType, $lines);
        $this->assertContains(GiftType::PRODUCT, $types);
        $this->assertContains(GiftType::SUBSCRIPTION, $types);
    }

    // -------------------------------------------------------------------------
    // Exclusive suppression
    // -------------------------------------------------------------------------

    public function test_exclusive_suppresses_non_exclusive_in_same_merchant(): void
    {
        $exclusive = $this->makeCandidate(1, giftProductId: 10, exclusive: true, merchantId: 1);
        $nonExclusive = $this->makeCandidate(2, giftProductId: 20, exclusive: false, merchantId: 1);

        $lines = $this->strategy->resolve([$exclusive, $nonExclusive]);

        $this->assertCount(1, $lines);
        $this->assertEquals(10, $lines[0]->giftProductId);
    }

    public function test_multiple_exclusive_promotions_all_survive(): void
    {
        $exclusiveA = $this->makeCandidate(1, giftProductId: 10, exclusive: true, merchantId: 1);
        $exclusiveB = $this->makeCandidate(2, giftProductId: 20, exclusive: true, merchantId: 1);

        $lines = $this->strategy->resolve([$exclusiveA, $exclusiveB]);

        $this->assertCount(2, $lines);
    }

    public function test_no_exclusive_means_all_promotions_stack(): void
    {
        $promoA = $this->makeCandidate(1, giftProductId: 10, exclusive: false, merchantId: 1);
        $promoB = $this->makeCandidate(2, giftProductId: 20, exclusive: false, merchantId: 1);
        $promoC = $this->makeCandidate(3, giftProductId: 30, exclusive: false, merchantId: 1);

        $lines = $this->strategy->resolve([$promoA, $promoB, $promoC]);

        $this->assertCount(3, $lines);
    }

    // -------------------------------------------------------------------------
    // Merchant scope isolation
    // -------------------------------------------------------------------------

    public function test_merchant_a_exclusive_does_not_suppress_merchant_b(): void
    {
        $merchantAExclusive = $this->makeCandidate(1, giftProductId: 10, exclusive: true, merchantId: 1);
        $merchantBNormal = $this->makeCandidate(2, giftProductId: 20, exclusive: false, merchantId: 2);

        $lines = $this->strategy->resolve([$merchantAExclusive, $merchantBNormal]);

        $this->assertCount(2, $lines);

        $giftProductIds = array_map(fn($l) => $l->giftProductId, $lines);
        $this->assertContains(10, $giftProductIds);
        $this->assertContains(20, $giftProductIds);
    }

    public function test_platform_exclusive_does_not_suppress_merchant_gifts(): void
    {
        $platformExclusive = $this->makeCandidate(1, giftProductId: 10, exclusive: true, merchantId: null);
        $merchantGift = $this->makeCandidate(2, giftProductId: 20, exclusive: false, merchantId: 5);

        $lines = $this->strategy->resolve([$platformExclusive, $merchantGift]);

        $this->assertCount(2, $lines);
    }

    public function test_merchant_exclusive_does_not_suppress_platform_gifts(): void
    {
        $merchantExclusive = $this->makeCandidate(1, giftProductId: 10, exclusive: true, merchantId: 3);
        $platformGift = $this->makeCandidate(2, giftProductId: 20, exclusive: false, merchantId: null);

        $lines = $this->strategy->resolve([$merchantExclusive, $platformGift]);

        $this->assertCount(2, $lines);
    }

    public function test_two_merchants_with_independent_exclusivity(): void
    {
        // M1: exclusive suppresses its non-exclusive
        $m1Exclusive = $this->makeCandidate(1, giftProductId: 10, exclusive: true, merchantId: 1);
        $m1NonExclusive = $this->makeCandidate(2, giftProductId: 11, exclusive: false, merchantId: 1);

        // M2: no exclusive, all stack
        $m2GiftA = $this->makeCandidate(3, giftProductId: 20, exclusive: false, merchantId: 2);
        $m2GiftB = $this->makeCandidate(4, giftProductId: 21, exclusive: false, merchantId: 2);

        $lines = $this->strategy->resolve([$m1Exclusive, $m1NonExclusive, $m2GiftA, $m2GiftB]);

        $giftIds = array_map(fn($l) => $l->giftProductId, $lines);
        $this->assertCount(3, $lines);
        $this->assertContains(10, $giftIds, 'M1 exclusive survives');
        $this->assertNotContains(11, $giftIds, 'M1 non-exclusive suppressed');
        $this->assertContains(20, $giftIds, 'M2 gift A survives');
        $this->assertContains(21, $giftIds, 'M2 gift B survives');
    }

    // -------------------------------------------------------------------------
    // Priority
    // -------------------------------------------------------------------------

    public function test_highest_priority_promotion_is_source_for_merged_line(): void
    {
        $lowPriority = $this->makeCandidate(1, giftProductId: 10, priority: 1);
        $highPriority = $this->makeCandidate(2, giftProductId: 10, priority: 99);

        // Pass low first — strategy must sort by priority before processing
        $lines = $this->strategy->resolve([$lowPriority, $highPriority]);

        $this->assertEquals(2, $lines[0]->sourcePromotionId,
            'sourcePromotionId must come from highest-priority promotion'
        );
    }

    // -------------------------------------------------------------------------
    // Label fallback
    // -------------------------------------------------------------------------

    public function test_uses_free_gift_fallback_label_when_none_provided(): void
    {
        $candidate = $this->makeCandidate(1, giftProductId: 10);

        $lines = $this->strategy->resolve([$candidate], []);

        $this->assertEquals('Free Gift', $lines[0]->label);
    }

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    private function makeCandidate(
        int              $promotionId,
        ?int             $giftProductId = null,
        ?int             $giftSubscriptionPlanId = null,
        int              $triggerCount = 1,
        GiftQuantityRule $quantityRule = GiftQuantityRule::ONE_PER_QUALIFYING,
        int              $maxPerOrder = 10,
        bool             $exclusive = false,
        int              $priority = 0,
        ?int             $merchantId = null,
    ): PromotionCandidate
    {
        $giftType = $giftSubscriptionPlanId !== null ? GiftType::SUBSCRIPTION : GiftType::PRODUCT;

        if ($giftProductId === null && $giftSubscriptionPlanId === null) {
            $giftProductId = 100 + $promotionId;
        }

        return new PromotionCandidate(
            promotionId: $promotionId,
            merchantId: $merchantId,
            giftType: $giftType,
            giftProductId: $giftProductId,
            giftSubscriptionPlanId: $giftSubscriptionPlanId,
            quantityRule: $quantityRule,
            maxPerOrder: $maxPerOrder,
            exclusive: $exclusive,
            priority: $priority,
            triggerCount: $triggerCount,
        );
    }
}