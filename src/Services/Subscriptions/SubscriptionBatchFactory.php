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
        private readonly SubscriptionPricingService $pricingCalculator,
        private readonly MemberResolver             $memberResolver,
    )
    {
    }

    /**
     * Create multiple pending subscriptions with calculated pricing.
     *
     * Gift logic:
     *   - When an item carries gift fields (gift_email, gift_first_name, …),
     *     the subscription is owned by the resolved recipient Member.
     *   - The buyer Member is recorded as gifted_by_member_id for audit.
     *   - Non-gift items always use the buyer Member (backward-compatible).
     *
     * Voucher logic:
     *   - Bundle items carry a pre-allocated price and cannot be discounted
     *     further.  The voucher is never offered to them.
     *   - For standard items the voucher is applied to the first eligible item
     *     only.  Once used it is not offered again (prevents double redemption).
     *
     * @return array<array{subscription: Subscription, pricing: SubscriptionPricing}>
     */
    public function createPendingSubscriptions(
        array  $cartItems,
        array  $checkoutData,
        Member $buyer,
        int                $siteId,
        ?ResolvedDiscounts $resolvedDiscounts,
    ): array
    {
        $subscriptions = [];
        $voucherCode = $checkoutData['voucher_code'] ?? null;
        $voucherUsed = false;

        // Gift fields are global to the checkout form (one recipient per order).
        // We merge them into every item so that resolveMember() works uniformly
        // per-item, which also supports future per-item gift targeting.
        $giftFields = $this->extractGiftFields($checkoutData);

        foreach ($cartItems as $item) {
            $itemData = array_merge($item, $giftFields, ['site_id' => $siteId]);

            // Resolve ownership: buyer for regular items, recipient for gifts.
            $ownerMember = $this->memberResolver->resolve($itemData, $buyer);

            // Bundle items cannot receive a voucher — their price is already
            // set by SubscriptionBundlePriceAllocator; stacking is unsupported.
            $isBundleItem = $this->isBundleItem($item);

            $itemVoucherCode = (!$isBundleItem && !$voucherUsed && $voucherCode)
                ? $voucherCode
                : null;

            $pricing = $this->pricingCalculator->calculateForCartItem(
                $item,
                $itemVoucherCode,
                $buyer,            // pricing always uses buyer for tax/address context
                $checkoutData
            );

            if ($pricing->voucherId) {
                $voucherUsed = true;
            }

            $isGift = $ownerMember->id !== $buyer->id;
            $giftedByMemberId = $isGift ? $buyer->id : null;

            $subscription = $this->subscriptionService->createOneTimeSubscription(
                memberId: $ownerMember->id,
                planId: $item['subscription_plan_id'],
                deliveryType: $pricing->deliveryType,
                siteId: $siteId,
                voucherId: $pricing->voucherId,
                pricing: $pricing,
                status: SubscriptionStatus::PENDING,
                selectedStartDate: $item['options']['start_date'] ?? null,
                giftedByMemberId: $giftedByMemberId,
            );

            $subscriptions[] = [
                'subscription' => $subscription,
                'pricing' => $pricing,
                'meta' => $this->mergeMetaData($item),
                'selected_start_date' => $item['options']['start_date'] ?? null,
            ];
        }

        return $subscriptions;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Extract gift-recipient fields from the flat checkout payload.
     *
     * The checkout form (gift-fields.php) posts these top-level keys.
     * We normalise them to the canonical gift_* prefix expected by MemberResolver.
     */
    private function extractGiftFields(array $checkoutData): array
    {
        if (empty($checkoutData['is_gift'])) {
            return [];
        }

        return array_filter([
            'gift_email' => $checkoutData['recipient_email'] ?? null,
            'gift_first_name' => $checkoutData['recipient_first_name'] ?? null,
            'gift_last_name' => $checkoutData['recipient_last_name'] ?? null,
            'gift_mobile' => $checkoutData['recipient_mobile'] ?? null,
        ], fn($v) => $v !== null);
    }

    /**
     * A cart item originates from a bundle when it carries a bundle_id in its
     * options, or its type is SUBSCRIPTION_BUNDLE.
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
            array_fill_keys($metaKeys, null),
            array_intersect_key($item, array_flip($metaKeys))
        );
    }
}