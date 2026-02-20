<?php

namespace App\Services\Subscriptions;

use App\DTO\Subscriptions\SubscriptionPricing;
use App\Enums\CartItemType;
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
     * Create multiple pending subscriptions with calculated pricing.
     *
     * Voucher logic:
     *   - Bundle items carry a pre-allocated price and cannot be discounted further.
     *     The voucher is never offered to them.
     *   - For standard items the voucher is applied to the first eligible item only.
     *     Once used it is not offered again (prevents multiple redemptions).
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
            // Bundle items cannot receive a voucher — their price is already set
            // by SubscriptionBundlePriceAllocator and voucher stacking is not supported.
            $isBundleItem = $this->isBundleItem($item);

            // Only offer the voucher to the first non-bundle item that has not yet used it
            $itemVoucherCode = (!$isBundleItem && !$voucherUsed && $voucherCode)
                ? $voucherCode
                : null;

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
                pricing: $pricing,
                status: SubscriptionStatus::PENDING,
                selectedStartDate: $item['options']['start_date'] ?? null
            );

            $subscriptions[] = [
                'subscription' => $subscription,
                'pricing' => $pricing,
                'meta' => $this->mergeMetaData($item),
            ];
        }

        return $subscriptions;
    }

    // -----------------------------------------------------------------------
    // Private
    // -----------------------------------------------------------------------

    /**
     * A cart item originates from a bundle when it carries a bundle_id in its options.
     */
    private function isBundleItem(array $item): bool
    {
        $options = $item['options'] ?? [];

        return isset($options['bundle_id'])
            || ($options['type'] ?? null) === CartItemType::SUBSCRIPTION_BUNDLE->value;
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
            array_fill_keys($metaKeys, null),           // default values
            array_intersect_key($item, array_flip($metaKeys)) // override with actual $item values
        );
    }
}