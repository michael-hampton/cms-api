<?php

namespace App\Services\Subscriptions;

use App\DTO\Subscriptions\SubscriptionPricing;
use App\Enums\Subscriptions\SubscriptionStatus;
use App\Models\Member;
use App\Models\Subscription;
use App\Services\Shopping\OneTimeSubscriptionService;
use App\Services\Vouchers\ResolvedDiscounts;

class SubscriptionBatchFactory
{
    public function __construct(
        private readonly OneTimeSubscriptionService $subscriptionService,
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
        int                $siteId,
        ?ResolvedDiscounts $resolvedDiscounts,
    ): array
    {
        $subscriptions = [];
        $voucherCode = $checkoutData['voucher_code'] ?? null;
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
                discountAmountCents: $resolvedDiscounts ? $resolvedDiscounts->getTotalDiscountCents() / 100 : $pricing->getDiscount(),
                status: SubscriptionStatus::PENDING,
                selectedStartDate: $item['options']['start_date'] ?? null,
                accessStartsAt: $item['estimated_delivery_to'],
                firstShipmentAt: $item['estimated_delivery_to'] ?? null,
            );

            $subscriptions[] = [
                'subscription' => $subscription,
                'pricing' => $pricing,
                'meta' => $this->mergeMetaData($item)
            ];
        }

        return $subscriptions;
    }

    private function mergeMetaData(array $item): array
    {
        $metaKeys = [
            'is_pre_release',
            'release_date',
            'expected_ship_date',
            'is_preorder',
            'next_issue_id',
            'next_issue_number',
            'next_issue_title',
            'next_issue_on_sale_date',
            'availability_message',
            'estimated_dispatch',
            'estimated_delivery_from',
            'estimated_delivery_to',
            'estimated_delivery_formatted',
        ];

        return array_merge(
            array_fill_keys($metaKeys, null), // default values
            array_intersect_key($item, array_flip($metaKeys)) // override with actual $item values
        );
    }
}