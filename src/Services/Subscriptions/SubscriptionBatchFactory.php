<?php

namespace App\Services\Subscriptions;

use App\DTO\Subscriptions\SubscriptionPricing;
use App\Enums\CartItemType;
use App\Enums\Subscriptions\SubscriptionStatus;
use App\Models\Member;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Shopping\OneTimeSubscriptionService;
use App\Services\Vouchers\ResolvedDiscounts;

class SubscriptionBatchFactory
{
    public function __construct(
        private readonly OneTimeSubscriptionService $subscriptionService,
        private readonly SubscriptionPricingService $pricingCalculator,
        private readonly MemberResolver $memberResolver,
        private readonly ?SubscriptionRepository $subscriptionRepository = null,
        private readonly ?RenewalIssueSchedulingService $renewalIssueSchedulingService = null,
    ) {
    }

    /**
     * @return array<array{subscription: Subscription, pricing: SubscriptionPricing}>
     */
    public function createPendingSubscriptions(
        array $cartItems,
        array $checkoutData,
        Member $buyer,
        int $siteId,
        ?ResolvedDiscounts $resolvedDiscounts,
    ): array {
        $subscriptions = [];
        $voucherCode = $checkoutData['voucher_code'] ?? null;
        $voucherUsed = false;
        $resubscribeFromSubscriptionId = $this->normaliseSourceSubscriptionId(
            $checkoutData['resubscribe_from_subscription_id'] ?? null,
        );
        $giftFields = $this->extractGiftFields($checkoutData);

        foreach ($cartItems as $item) {
            $itemData = array_merge($item, $giftFields, ['site_id' => $siteId]);
            $ownerMember = $this->memberResolver->resolve($itemData, $buyer);
            $isBundleItem = $this->isBundleItem($item);

            $itemVoucherCode = (!$isBundleItem && !$voucherUsed && $voucherCode)
                ? $voucherCode
                : null;

            $pricing = $this->pricingCalculator->calculateForCartItem(
                $item,
                $itemVoucherCode,
                $buyer,
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

            if (!$isGift && $resubscribeFromSubscriptionId !== null) {
                $this->tagResubscribeSource(
                    sourceSubscriptionId: $resubscribeFromSubscriptionId,
                    newSubscription: $subscription,
                    buyer: $buyer,
                    siteId: $siteId,
                    planId: (int) $item['subscription_plan_id'],
                );
            }

            $subscriptions[] = [
                'subscription' => $subscription,
                'pricing' => $pricing,
                'price_paid_cents' => $pricing->totalCents,
                'meta' => $this->mergeMetaData($item),
                'selected_start_date' => $item['options']['start_date'] ?? null,
            ];
        }

        return $subscriptions;
    }

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

    private function isBundleItem(array $item): bool
    {
        $options = $item['options'] ?? [];

        return isset($options['bundle_id'])
            || ($options['type'] ?? null) === CartItemType::SUBSCRIPTION_BUNDLE->value;
    }

    private function normaliseSourceSubscriptionId(mixed $value): ?int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    private function tagResubscribeSource(
        int $sourceSubscriptionId,
        Subscription $newSubscription,
        Member $buyer,
        int $siteId,
        int $planId,
    ): void {
        $source = $this->findSubscription($sourceSubscriptionId);

        if (!$source) {
            return;
        }

        if ((int) $source->member_id !== (int) $buyer->id) {
            return;
        }

        if ((int) $source->site_id !== $siteId || (int) $source->plan_id !== $planId) {
            return;
        }

        $newSubscription->update([
            'renewed_from_subscription_id' => $source->id,
            'replacement_reason' => 'resubscribe',
        ]);

        $this->renewalIssueSchedulingService?->scheduleForSubscription($newSubscription);
    }

    private function findSubscription(int $subscriptionId): ?Subscription
    {
        if ($this->subscriptionRepository !== null) {
            return $this->subscriptionRepository->find($subscriptionId);
        }

        return Subscription::find($subscriptionId);
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
            array_intersect_key($item, array_flip(keys($metaKeys)))
        );
    }
}
