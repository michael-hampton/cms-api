<?php

namespace App\DTO\Vouchers;

class VoucherValidationContext
{
    public function __construct(
        public readonly ?int  $userId,
        public readonly float $orderValue,
        public readonly array $cartItems,
        public readonly ?int  $productId = null,
        public readonly ?int  $subscriptionPlanId = null,
        public readonly bool   $hasOfferDiscount = false,
        public readonly ?float $finalPrice = null,
        public readonly bool   $forCart = true

    )
    {
    }

    public static function forCheckout(
        array $cartItems,
        ?int  $userId = null,
        bool  $hasOfferDiscount = false
    ): self
    {
        $orderValue = array_sum(array_column($cartItems, 'subtotal'));

        return new self(
            userId: $userId,
            orderValue: $orderValue,
            cartItems: $cartItems,
            hasOfferDiscount: $hasOfferDiscount
        );
    }

    public static function forSubscription(
        int   $planId,
        float $planPrice,
        ?int  $userId = null
    ): self
    {
        return new self(
            userId: $userId,
            orderValue: $planPrice,
            cartItems: [],
            subscriptionPlanId: $planId,
        );
    }

    public static function forProduct(
        int   $productId,
        float $orderValue,
        ?int $userId = null,
        bool $forCart = true
    ): self
    {
        return new self(
            userId: $userId,
            orderValue: $orderValue,
            cartItems: [],
            productId: $productId,
            forCart: $forCart
        );
    }
}