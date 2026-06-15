<?php

namespace App\Services\Subscriptions;

use App\DTO\Subscriptions\SubscriptionPricing;
use App\Enums\CartItemType;
use App\Enums\Subscriptions\SubscriptionType;
use App\Models\Member;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Services\Shipping\ShippingService;
use App\Services\Subscriptions\Calculators\SubscriptionPricingResolver;
use App\Services\Vouchers\VoucherService;

class SubscriptionPricingService
{
    public function __construct(
        private readonly SubscriptionPlanRepository  $planRepository,
        private readonly VoucherService              $voucherService,
        private readonly ShippingService             $shippingService,
        private readonly SubscriptionPricingResolver $pricingResolver
    )
    {
    }

    /**
     * Calculate pricing for a single subscription item.
     *
     * For bundle items the price was already allocated by SubscriptionBundlePriceAllocator
     * at add-to-cart time and stored on the cart row. We must honour that price exactly
     * — re-resolving through tier/voucher logic would corrupt the bundle's price
     * guarantee (the allocator ensures items sum to bundle_price cent-perfectly).
     *
     * Vouchers cannot be stacked on top of bundle pricing; any voucher code passed in
     * is silently ignored for bundle items.
     *
     * Returns all amounts in CENTS to avoid float precision issues.
     */
    public function calculateForCartItem(
        array   $item,
        ?string $voucherCode,
        Member  $member,
        array   $checkoutData
    ): SubscriptionPricing
    {
        if ($this->isBundleItem($item)) {
            return $this->calculateForBundleItem($item, $checkoutData);
        }

        return $this->calculateForStandardItem($item, $voucherCode, $member, $checkoutData);
    }

    // -----------------------------------------------------------------------
    // Private: routing helpers
    // -----------------------------------------------------------------------

    /**
     * A cart item is a bundle item when it carries a bundle_id in its options.
     * We check the options array directly so this works regardless of whether
     * CartItemType::SUBSCRIPTION_BUNDLE is set (defensive).
     */
    private function isBundleItem(array $item): bool
    {
        $options = $item['options'] ?? [];

        return isset($options['bundle_id'])
            || ($options['type'] ?? null) === CartItemType::SUBSCRIPTION_BUNDLE->value;
    }

    // -----------------------------------------------------------------------
    // Private: bundle path
    // -----------------------------------------------------------------------

    /**
     * Use the cart item's price as-is — it was already allocated by
     * SubscriptionBundlePriceAllocator.
     *
     * Vouchers are never applied here: a bundle is already a discounted price
     * and voucher stacking on bundles is not supported.
     *
     * Shipping is still calculated for print delivery because the fulfilment
     * cost is independent of the subscription price.
     */
    private function calculateForBundleItem(array $item, array $checkoutData): SubscriptionPricing
    {
        $deliveryType = $item['options']['delivery_type'] ?? SubscriptionType::DIGITAL->value;

        // The authoritative price was set by the allocator — convert to cents.
        $subtotalCents = (int)round(($item['price'] ?? 0) * 100);

        $shippingCents = 0;
        if ($deliveryType === SubscriptionType::PRINTED->value) {
            $shippingAmount = $this->shippingService->calculateShipping(
                $subtotalCents / 100,
                $checkoutData
            );
            $shippingCents = (int)round($shippingAmount * 100);
        }

        $totalCents = $subtotalCents + $shippingCents;

        return new SubscriptionPricing(
            subtotalCents: $subtotalCents,
            discountCents: 0,   // bundle discount already baked in at add-to-cart
            shippingCents: $shippingCents,
            taxCents: 0,
            totalCents: $totalCents,
            deliveryType: $deliveryType,
            voucherId: null, // vouchers cannot stack on bundle pricing
            shippingAddressSnapshot: $deliveryType === SubscriptionType::PRINTED->value
                ? $this->captureShippingAddress($checkoutData)
                : null,
            originalAmount: $item['price'] ?? 0,
            pricingTierId: $item['options']['pricing_tier_id'] ?? null,
            currency: $item['currency'] ?? null,
        );
    }

    // -----------------------------------------------------------------------
    // Private: standard (non-bundle) path
    // -----------------------------------------------------------------------

    /**
     * Original pricing logic: resolve through tier/voucher, calculate shipping.
     */
    private function calculateForStandardItem(
        array   $item,
        ?string $voucherCode,
        Member  $member,
        array   $checkoutData
    ): SubscriptionPricing
    {
        // Get authoritative plan (don't trust cart data)
        $plan = $this->planRepository->find($item['subscription_plan_id']);
        if (!$plan) {
            throw new \InvalidArgumentException('Invalid subscription plan');
        }

        $deliveryType = $item['options']['delivery_type'] ?? SubscriptionType::DIGITAL->value;

        // Build resolver data from cart item
        $resolverData = [
            'variant' => $deliveryType,
            'pricing_tier_id' => $item['options']['pricing_tier_id'] ?? null,
            'voucher_code' => $voucherCode,
        ];

        // Resolve pricing using tier-aware resolver
        $resolvedPrice = $this->pricingResolver->resolve($plan, $resolverData, $member->id);

        // Convert final price to cents
        $subtotalCents = (int)round($resolvedPrice->finalPrice * 100);
        $discountCents = (int)round($resolvedPrice->discountAmount * 100);
        $originalAmount = $resolvedPrice->salePrice < $resolvedPrice->basePrice
            ? $resolvedPrice->salePrice
            : $resolvedPrice->basePrice;
        $voucherId = $resolvedPrice->voucherId;

        if ($voucherCode && $voucherId && $discountCents <= 0) {
            $discountCents = $this->postedVoucherDiscountCents($checkoutData, $subtotalCents);
            $subtotalCents = max(0, $subtotalCents - $discountCents);
        }

        $afterDiscountCents = $subtotalCents;

        // Calculate shipping for print delivery
        $shippingCents = 0;
        if ($deliveryType === SubscriptionType::PRINTED->value) {
            $shippingAmount = $this->shippingService->calculateShipping(
                $afterDiscountCents / 100,
                $checkoutData
            );
            $shippingCents = (int)round($shippingAmount * 100);
        }

        // Tax calculation happens at order level, not per item
        $taxCents = 0;
        $totalCents = $afterDiscountCents + $shippingCents + $taxCents;

        return new SubscriptionPricing(
            subtotalCents: $subtotalCents,
            discountCents: $discountCents,
            shippingCents: $shippingCents,
            taxCents: $taxCents,
            totalCents: $totalCents,
            deliveryType: $deliveryType,
            voucherId: $voucherId,
            shippingAddressSnapshot: $deliveryType === SubscriptionType::PRINTED->value
                ? $this->captureShippingAddress($checkoutData)
                : null,
            originalAmount: $originalAmount,
            pricingTierId: $resolvedPrice->pricingTierId,
            currency: $resolvedPrice->currency,
        );
    }

    // -----------------------------------------------------------------------
    // Private: shared
    // -----------------------------------------------------------------------

    private function captureShippingAddress(array $data): ?array
    {
        if (empty($data['address'])) {
            return null;
        }

        return [
            'address_line_1' => $data['address'],
            'address_line_2' => $data['address2'] ?? '',
            'city' => $data['city'] ?? '',
            'state' => $data['state'] ?? '',
            'postcode' => $data['postal_code'] ?? '',
            'country' => $data['country'] ?? '',
        ];
    }

    private function postedVoucherDiscountCents(array $checkoutData, int $maxDiscountCents): int
    {
        if (empty($checkoutData['voucher_code']) || !isset($checkoutData['discount_amount'])) {
            return 0;
        }

        $discountCents = (int)round((float)$checkoutData['discount_amount'] * 100);

        return max(0, min($discountCents, $maxDiscountCents));
    }
}
