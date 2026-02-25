<?php

namespace App\DTO\Cart;

use App\Enums\Gifts\GiftQuantityRule;
use App\Enums\Gifts\GiftType;

/**
 * Normalised representation of a gift promotion that has been determined eligible
 * for the current cart.
 *
 * Produced by GiftEligibilityCollector.
 * Consumed by GiftResolutionStrategy.
 *
 * At this point eligibility has already been confirmed — every trigger group
 * has been evaluated. This DTO carries only what the resolver needs to decide
 * which promotions survive (exclusive suppression, quantity calculation).
 */
final class PromotionCandidate
{
    public function __construct(
        public readonly int              $promotionId,

        /** null = platform-level promotion */
        public readonly ?int             $merchantId,

        public readonly GiftType         $giftType,

        /** Set when giftType = PRODUCT */
        public readonly ?int             $giftProductId,

        /** Set when giftType = SUBSCRIPTION */
        public readonly ?int             $giftSubscriptionPlanId,

        public readonly GiftQuantityRule $quantityRule,
        public readonly int              $maxPerOrder,
        public readonly bool             $exclusive,
        public readonly int              $priority,

        /**
         * How many times this promotion was triggered by items in the cart.
         * Used by the resolver to calculate final gift quantity under
         * ONE_PER_QUALIFYING rule.
         */
        public readonly int              $triggerCount,
    )
    {
    }

    /**
     * A stable key identifying what is being gifted.
     * Used by the resolver to detect and merge duplicate gift targets.
     */
    public function giftKey(): string
    {
        return match ($this->giftType) {
            GiftType::PRODUCT => "product:{$this->giftProductId}",
            GiftType::SUBSCRIPTION => "subscription:{$this->giftSubscriptionPlanId}",
        };
    }

    public function withTriggerCount(int $count): self
    {
        return new self(
            promotionId: $this->promotionId,
            merchantId: $this->merchantId,
            giftType: $this->giftType,
            giftProductId: $this->giftProductId,
            giftSubscriptionPlanId: $this->giftSubscriptionPlanId,
            quantityRule: $this->quantityRule,
            maxPerOrder: $this->maxPerOrder,
            exclusive: $this->exclusive,
            priority: $this->priority,
            triggerCount: $count,
        );
    }
}