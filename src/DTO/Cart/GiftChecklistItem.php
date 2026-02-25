<?php

namespace App\DTO\Cart;

use App\Enums\CartItemType;

/**
 * Describes one gift attached to a checklist item.
 *
 * A gift can be either a product or a subscription plan.  Exactly one of
 * $productId / $subscriptionPlanId must be non-null.
 */
final class GiftChecklistItem
{
    public function __construct(
        /** Human-readable label shown in the cart and on the order. */
        public readonly string $label,

        /** Set when the gift is a physical or digital product. */
        public readonly ?int   $productId = null,

        /** Set when the gift is a subscription plan (one-time or recurring). */
        public readonly ?int   $subscriptionPlanId = null,

        /** For subscription gifts: which delivery type to apply. */
        public readonly string $deliveryType = 'digital',

        /** Variant ID, only relevant for product gifts. */
        public readonly ?int   $variantId = null,

        /** Quantity of the gift (almost always 1). */
        public readonly int    $quantity = 1,
    )
    {
        if ($productId === null && $subscriptionPlanId === null) {
            throw new \InvalidArgumentException(
                'A GiftChecklistItem must specify either a productId or a subscriptionPlanId.'
            );
        }

        if ($productId !== null && $subscriptionPlanId !== null) {
            throw new \InvalidArgumentException(
                'A GiftChecklistItem cannot specify both a productId and a subscriptionPlanId.'
            );
        }
    }

    public function isProduct(): bool
    {
        return $this->productId !== null;
    }

    public function isSubscription(): bool
    {
        return $this->subscriptionPlanId !== null;
    }

    /**
     * Serialise to the JSON that is stored in the cart item options and
     * subsequently on the order line metadata.
     */
    public function toMetadata(): array
    {
        return [
            'type' => CartItemType::FREE_GIFT->value,
            'label' => $this->label,
            'product_id' => $this->productId,
            'subscription_plan_id' => $this->subscriptionPlanId,
            'delivery_type' => $this->deliveryType,
            'variant_id' => $this->variantId,
            'quantity' => $this->quantity,
            'is_gift' => true,
        ];
    }
}