<?php

namespace App\Services\Subscriptions;

use App\DTO\Subscriptions\SubscriptionPricing;
use App\Enums\Subscriptions\SubscriptionStatus;
use App\Models\Member;
use App\Models\Subscription;
use App\Services\Shopping\OneTimeSubscriptionService;

class SubscriptionBatchFactory
{
    public function __construct(
        private readonly OneTimeSubscriptionService    $subscriptionService,
        private readonly SubscriptionPricingService $pricingCalculator
    )
    {
    }

    /**
     * Create multiple pending subscriptions with calculated pricing
     *
     * @return array<array{subscription: Subscription, pricing: SubscriptionPricing}>
     */
    public function createPendingSubscriptions(
        array  $cartItems,
        array  $checkoutData,
        Member $member,
        int    $siteId
    ): array
    {
        $subscriptions = [];
        $voucherCode = $checkoutData['voucher_code'] ?? null; //todo discount needs plugging in
        $voucherUsed = false;

        foreach ($cartItems as $item) {
            // Only apply voucher to first subscription (prevent multiple applications)
            $itemVoucherCode = (!$voucherUsed && $voucherCode) ? $voucherCode : null;

            $pricing = $this->pricingCalculator->calculateForCartItem(
                $item,
                $itemVoucherCode,
                $member,
                $checkoutData
            );

            if ($pricing->voucherId) {
                $voucherUsed = true;
            }

            // Create subscription in pending_payment status
            $subscription = $this->subscriptionService->createOneTimeSubscription(
                memberId: $member->id,
                planId: $item['subscription_plan_id'],
                deliveryType: $pricing->deliveryType,
                siteId: $siteId,
                voucherId: $pricing->voucherId,
                discountAmountCents: $pricing->getDiscount(),
                status: SubscriptionStatus::PENDING,
                selectedStartDate: $item['options']['start_date'] ?? null
            );

            $subscriptions[] = [
                'subscription' => $subscription,
                'pricing' => $pricing
            ];
        }

        return $subscriptions;
    }
}