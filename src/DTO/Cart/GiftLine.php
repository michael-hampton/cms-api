<?php

namespace App\DTO\Cart;

use App\Enums\Gifts\GiftType;

/**
 * Final output of the gift resolution pipeline.
 *
 * One GiftLine represents one distinct gift to be injected into the cart.
 * Multiple qualifying promotions targeting the same gift are merged into
 * a single GiftLine with an accumulated quantity.
 *
 * Consumed by GiftChecklistService to create FREE_GIFT cart items.
 */
final class GiftLine
{
    public function __construct(
        public readonly GiftType $giftType,

        /** Set when giftType = PRODUCT */
        public readonly ?int     $giftProductId,

        /** Set when giftType = SUBSCRIPTION */
        public readonly ?int     $giftSubscriptionPlanId,

        /** Final resolved quantity after applying quantity rules */
        public readonly int      $quantity,

        /**
         * The promotion that sourced this gift line.
         * When multiple promotions merge into one GiftLine (same gift target),
         * this is the highest-priority promotion's ID.
         */
        public readonly int      $sourcePromotionId,

        /**
         * Human-readable label for cart display and order line metadata.
         * Populated from the gift product/plan name by GiftResolutionService.
         */
        public readonly string   $label,
    )
    {
    }

    /**
     * Converts to the format expected by GiftChecklistItem constructor.
     */
    public function toGiftChecklistItem(): GiftChecklistItem
    {
        return new GiftChecklistItem(
            label: $this->label,
            productId: $this->giftProductId,
            subscriptionPlanId: $this->giftSubscriptionPlanId,
            quantity: $this->quantity,
        );
    }
}