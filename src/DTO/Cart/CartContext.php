<?php

namespace App\DTO\Cart;

use App\Models\CartItem;

/**
 * Immutable snapshot of cart state used by GiftEligibilityCollector.
 *
 * Decouples the collector from CartService internals — the collector
 * receives a plain value object it can interrogate without knowing
 * anything about sessions, repositories, or cart implementation details.
 */
final class CartContext
{
    /**
     * @param CartItem[] $lineItems All current cart line items
     * @param float $cartTotal Pre-discount, pre-gift subtotal
     * @param int $itemCount Total quantity of all line items (excluding gifts)
     * @param bool $isFirstOrder Whether this is the customer's first ever order
     * @param ?int $userId Authenticated user ID (null for guests)
     * @param ?int $merchantId Current merchant scope (null for platform cart)
     */
    public function __construct(
        public readonly array $lineItems,
        public readonly float $cartTotal,
        public readonly int   $itemCount,
        public readonly bool  $isFirstOrder,
        public readonly ?int  $userId,
        public readonly ?int  $merchantId,
    )
    {
    }

    /**
     * Returns product IDs present in the cart (excluding gift lines).
     *
     * @return int[]
     */
    public function productIds(): array
    {
        return array_values(array_filter(array_map(
            fn($line) => $line->productId,
            array_filter($this->lineItems, fn($line) => !$line->isGift)
        )));
    }

    /**
     * Returns subscription plan IDs present in the cart (excluding gift lines).
     *
     * @return int[]
     */
    public function subscriptionPlanIds(): array
    {
        return array_values(array_filter(array_map(
            fn($line) => $line->subscriptionPlanId,
            array_filter($this->lineItems, fn($line) => !$line->isGift)
        )));
    }

    /**
     * Returns category IDs across all non-gift cart products.
     *
     * @return int[]
     */
    public function categoryIds(): array
    {
        $ids = [];
        foreach ($this->lineItems as $line) {
            if (!$line->isGift) {
                foreach ($line->categoryIds as $id) {
                    $ids[] = $id;
                }
            }
        }
        return array_values(array_unique($ids));
    }
}