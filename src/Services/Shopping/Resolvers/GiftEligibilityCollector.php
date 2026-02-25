<?php

namespace App\Services\Shopping\Resolvers;

use App\DTO\Cart\CartContext;
use App\DTO\Cart\PromotionCandidate;
use App\Enums\Gifts\GiftTriggerOperator;
use App\Enums\Gifts\GiftTriggerType;
use App\Framework\Support\Collection;
use App\Repositories\Shopping\GiftPromotionRepository;

/**
 * Evaluates all active gift promotions against the current cart state and
 * returns those that are fully eligible as PromotionCandidate DTOs.
 *
 * Trigger evaluation logic:
 *   1. Load candidate promotions from the repository (broad, over-fetches).
 *   2. Load all triggers for those promotions.
 *   3. Group triggers by promotion_id → group_key.
 *   4. Within each group, ALL triggers must pass (AND semantics).
 *      If negated = true, the trigger must NOT be satisfied.
 *   5. A promotion is eligible if ANY group fully passes (OR semantics).
 *   6. triggerCount is calculated per group from entity triggers only.
 *
 * Entity triggers vs gate triggers:
 *   Entity triggers (product, subscription_plan, category) contribute to
 *   multiplicity — their match count determines how many gifts the customer
 *   earns under ONE_PER_QUALIFYING rules.
 *
 *   Gate triggers (cart_total, item_count, first_time_buyer) are pass/fail
 *   only. They do NOT contribute a count. Including them in min() would
 *   poison AND groups by reducing count to 0.
 *
 *   Final multiplicity for a group = min(entity trigger counts).
 *   If the group has no entity triggers (e.g. cart_total only), count = 1.
 */
class GiftEligibilityCollector
{
    /**
     * Trigger types that contribute multiplicity to AND group count.
     * All other types are gates — pass/fail only.
     */
    private const ENTITY_TRIGGER_TYPES = [
        GiftTriggerType::PRODUCT,
        GiftTriggerType::SUBSCRIPTION_PLAN,
        GiftTriggerType::CATEGORY,
    ];

    public function __construct(
        private readonly GiftPromotionRepository $repository,
    )
    {
    }

    /**
     * @return PromotionCandidate[]
     */
    public function collect(CartContext $cart): array
    {
        $promotions = $this->repository->findCandidatesForCart(
            productIds: $cart->productIds(),
            subscriptionPlanIds: $cart->subscriptionPlanIds(),
            categoryIds: $cart->categoryIds(),
            merchantIds: array_values(array_filter([$cart->merchantId])),
            includeFirstTimeBuyer: $cart->isFirstOrder,
        );

        if ($promotions->isEmpty()) {
            return [];
        }

        $triggersByPromotion = $this->repository->findTriggersForPromotions(
            $promotions->pluck('id')->toArray()
        );

        $candidates = [];

        foreach ($promotions as $promotion) {
            $triggers = $triggersByPromotion->get($promotion->id, collect());

            [$eligible, $triggerCount] = $this->evaluatePromotion($triggers, $cart);

            if (!$eligible) {
                continue;
            }

            $candidates[] = new PromotionCandidate(
                promotionId: $promotion->id,
                merchantId: $promotion->merchantId,
                giftType: $promotion->giftType,
                giftProductId: $promotion->giftProductId,
                giftSubscriptionPlanId: $promotion->giftSubscriptionPlanId,
                quantityRule: $promotion->quantityRule,
                maxPerOrder: $promotion->maxPerOrder,
                exclusive: $promotion->exclusive,
                priority: $promotion->priority,
                triggerCount: $triggerCount,
            );
        }

        return $candidates;
    }

    // -------------------------------------------------------------------------
    // Promotion evaluation
    // -------------------------------------------------------------------------

    /**
     * Evaluates all trigger groups for a promotion (OR across groups).
     * Returns [isEligible, triggerCount].
     *
     * @return array{bool, int}
     */
    private function evaluatePromotion(Collection $triggers, CartContext $cart): array
    {
        if ($triggers->isEmpty()) {
            return [false, 0];
        }

        $groups = $triggers->groupBy('groupKey');
        $bestTriggerCount = 0;

        foreach ($groups as $groupTriggers) {
            [$groupPasses, $groupCount] = $this->evaluateGroup($groupTriggers, $cart);

            if ($groupPasses) {
                // OR semantics: promotion is eligible as soon as any group passes.
                // Track the highest trigger count across all passing groups so
                // the resolver has the most accurate quantity signal.
                $bestTriggerCount = max($bestTriggerCount, $groupCount);
            }
        }

        return $bestTriggerCount > 0 || $this->anyGroupPassesWithNoEntityTriggers($groups, $cart)
            ? [true, max($bestTriggerCount, 1)]
            : [false, 0];
    }

    /**
     * Handles the edge case where a passing group has only gate triggers
     * (e.g. cart_total only). evaluateGroup returns count=0 in that case,
     * so we need a separate check to confirm eligibility.
     */
    private function anyGroupPassesWithNoEntityTriggers(Collection $groups, CartContext $cart): bool
    {
        foreach ($groups as $groupTriggers) {
            [$passes, $count] = $this->evaluateGroup($groupTriggers, $cart);
            if ($passes && $count === 0) {
                return true;
            }
        }
        return false;
    }

    // -------------------------------------------------------------------------
    // Group evaluation (AND logic)
    // -------------------------------------------------------------------------

    /**
     * Evaluates a single trigger group using AND logic.
     *
     * All triggers must pass. If any fails (accounting for negation), the
     * group fails immediately.
     *
     * Multiplicity:
     *   - Entity triggers contribute a match count.
     *   - Gate triggers are pass/fail only (count = 0).
     *   - Final group count = min(entity counts).
     *   - If no entity triggers in group, count = 0 (caller defaults to 1).
     *
     * @return array{bool, int}
     */
    private function evaluateGroup(Collection $groupTriggers, CartContext $cart): array
    {
        $entityCounts = [];

        foreach ($groupTriggers as $trigger) {
            [$passes, $count] = $this->evaluateTrigger($trigger, $cart);

            $shouldPass = $trigger->negated ? !$passes : $passes;

            if (!$shouldPass) {
                return [false, 0];
            }

            // Only entity triggers with positive counts contribute to multiplicity.
            // Gate triggers (count = 0) and negated entity triggers are excluded.
            if ($count > 0 && !$trigger->negated && $this->isEntityTrigger($trigger->type)) {
                $entityCounts[] = $count;
            }
        }

        // min() across entity counts: the AND group can only fire as many times
        // as the least-matched entity trigger allows.
        // e.g. 3x Product A AND 1x Product B → min(3, 1) = 1 qualifying event.
        $triggerCount = empty($entityCounts) ? 0 : min($entityCounts);

        return [true, $triggerCount];
    }

    // -------------------------------------------------------------------------
    // Individual trigger evaluation
    // -------------------------------------------------------------------------

    /**
     * @return array{bool, int}  [passes, matchCount]
     *   matchCount > 0 only for entity triggers.
     *   Gate triggers always return matchCount = 0.
     */
    private function evaluateTrigger(object $trigger, CartContext $cart): array
    {
        return match ($trigger->type) {
            GiftTriggerType::PRODUCT => $this->evaluateProductTrigger($trigger, $cart),
            GiftTriggerType::SUBSCRIPTION_PLAN => $this->evaluateSubscriptionTrigger($trigger, $cart),
            GiftTriggerType::CATEGORY => $this->evaluateCategoryTrigger($trigger, $cart),
            GiftTriggerType::CART_TOTAL => $this->evaluateNumericTrigger($trigger, $cart->cartTotal),
            GiftTriggerType::ITEM_COUNT => $this->evaluateNumericTrigger($trigger, (float)$cart->itemCount),
            GiftTriggerType::FIRST_TIME_BUYER => $this->evaluateFirstTimeBuyerTrigger($trigger, $cart),
        };
    }

    private function evaluateProductTrigger(object $trigger, CartContext $cart): array
    {
        $matchCount = 0;

        if ($trigger->operator === GiftTriggerOperator::EQUALS) {
            foreach ($cart->lineItems as $line) {
                if (!$line->isGift && $line->productId === $trigger->referenceId) {
                    $matchCount += $line->quantity;
                }
            }
            return [$matchCount > 0, $matchCount];
        }

        if ($trigger->operator === GiftTriggerOperator::IN) {
            $allowedIds = $trigger->valueSet ?? [];
            foreach ($cart->lineItems as $line) {
                if (!$line->isGift && $line->productId !== null && in_array($line->productId, $allowedIds)) {
                    $matchCount += $line->quantity;
                }
            }
            return [$matchCount > 0, $matchCount];
        }

        return [false, 0];
    }

    private function evaluateSubscriptionTrigger(object $trigger, CartContext $cart): array
    {
        $matchCount = 0;

        if ($trigger->operator === GiftTriggerOperator::EQUALS) {
            foreach ($cart->lineItems as $line) {
                if (!$line->isGift && $line->subscriptionPlanId === $trigger->referenceId) {
                    $matchCount += $line->quantity;
                }
            }
            return [$matchCount > 0, $matchCount];
        }

        if ($trigger->operator === GiftTriggerOperator::IN) {
            $allowedIds = $trigger->valueSet ?? [];
            foreach ($cart->lineItems as $line) {
                if (!$line->isGift
                    && $line->subscriptionPlanId !== null
                    && in_array($line->subscriptionPlanId, $allowedIds)
                ) {
                    $matchCount += $line->quantity;
                }
            }
            return [$matchCount > 0, $matchCount];
        }

        return [false, 0];
    }

    private function evaluateCategoryTrigger(object $trigger, CartContext $cart): array
    {
        $matchCount = 0;

        if ($trigger->operator === GiftTriggerOperator::EQUALS) {
            foreach ($cart->lineItems as $line) {
                if (!$line->isGift && in_array($trigger->referenceId, $line->categoryIds)) {
                    $matchCount += $line->quantity;
                }
            }
            return [$matchCount > 0, $matchCount];
        }

        if ($trigger->operator === GiftTriggerOperator::IN) {
            $allowedIds = $trigger->valueSet ?? [];
            foreach ($cart->lineItems as $line) {
                if (!$line->isGift && !empty(array_intersect($line->categoryIds, $allowedIds))) {
                    $matchCount += $line->quantity;
                }
            }
            return [$matchCount > 0, $matchCount];
        }

        return [false, 0];
    }

    /**
     * Gate trigger — pass/fail only, never contributes to multiplicity.
     *
     * @return array{bool, int}  int is always 0
     */
    private function evaluateNumericTrigger(object $trigger, float $cartValue): array
    {
        if ($trigger->value === null) {
            return [false, 0];
        }

        $passes = match ($trigger->operator) {
            GiftTriggerOperator::GREATER_THAN_OR_EQUAL => $cartValue >= $trigger->value,
            GiftTriggerOperator::LESS_THAN_OR_EQUAL => $cartValue <= $trigger->value,
            GiftTriggerOperator::EQUALS => $cartValue == $trigger->value,
            default => false,
        };

        return [$passes, 0];
    }

    /**
     * Gate trigger — boolean pass/fail, never contributes to multiplicity.
     *
     * Operator must always be EQUALS for FIRST_TIME_BUYER. referenceId,
     * value, and valueSet must be null. These constraints are enforced at
     * serialisation time in GiftPromotionRepository::serialiseTrigger().
     *
     * @return array{bool, int}  int is always 0
     */
    private function evaluateFirstTimeBuyerTrigger(object $trigger, CartContext $cart): array
    {
        return [$cart->isFirstOrder, 0];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function isEntityTrigger(GiftTriggerType $type): bool
    {
        return in_array($type, self::ENTITY_TRIGGER_TYPES, strict: true);
    }
}