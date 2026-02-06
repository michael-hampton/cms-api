<?php

namespace App\Services\Subscriptions;

use App\DTO\Subscriptions\SubscriptionPricing;
use App\Models\Member;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Services\Shopping\ShippingService;
use App\Services\Vouchers\VoucherService;

class SubscriptionPricingService
{
    public function __construct(
        private readonly SubscriptionPlanRepository $planRepository,
        private readonly VoucherService             $voucherService,
        private readonly ShippingService            $shippingService
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
        // Get authoritative plan price (don't trust cart price)
        $plan = $this->planRepository->find($item['subscription_plan_id']);
        if (!$plan) {
            throw new \InvalidArgumentException('Invalid subscription plan');
        }

        $deliveryType = $item['options']['delivery_type'] ?? 'digital';

        // Convert to cents immediately
        $subtotalCents = (int)round($plan->price * 100);
        $discountCents = 0;
        $voucherId = null;

        // Apply voucher if provided
        if ($voucherCode) {
            $voucherValidation = $this->voucherService->validateVoucherForSubscription(
                $voucherCode,
                $plan->id,
                $member->id
            );

            if ($voucherValidation->valid) {
                $voucherId = $voucherValidation->voucherId;
                $discountCents = (int)round($voucherValidation->discount * 100);
            }
        }

        $afterDiscountCents = $subtotalCents - $discountCents;

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