<?php

namespace App\Services\Subscriptions;

use App\DTO\Subscriptions\SubscriptionPricing;
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
     * Calculate pricing for a single subscription item
     * Returns all amounts in CENTS to avoid float precision issues
     */
    public function calculateForCartItem(
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

        $deliveryType = $item['options']['delivery_type'] ?? 'digital';

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
        $voucherId = $resolvedPrice->voucherId;

        $afterDiscountCents = $subtotalCents;

        // Calculate shipping for print delivery
        $shippingCents = 0;
        if ($deliveryType === 'print') {
            $shippingAmount = $this->shippingService->calculateShipping(
                $afterDiscountCents / 100, // Convert back to dollars for legacy method
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
            shippingAddressSnapshot: $deliveryType === 'print' ? $this->captureShippingAddress($checkoutData) : null
        );
    }

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
            'country' => $data['country'] ?? ''
        ];
    }
}