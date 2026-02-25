<?php

namespace App\DTO\Cart;

use App\Enums\CartItemType;

/**
 * Immutable representation of a single cart line item for use within
 * the gift promotion pipeline.
 *
 * Stripped of session/DB concerns — only carries what the eligibility
 * collector needs to evaluate trigger conditions.
 */
final class CartLineItem
{
    /**
     * @param int[] $categoryIds Category IDs the product belongs to
     */
    public function __construct(
        public readonly int          $cartItemId,
        public readonly CartItemType $type,
        public readonly ?int         $productId,
        public readonly ?int         $subscriptionPlanId,
        public readonly float        $price,
        public readonly int          $quantity,
        public readonly bool         $isGift,
        public readonly ?int         $merchantId,
        public readonly array        $categoryIds = [],
    )
    {
    }
}