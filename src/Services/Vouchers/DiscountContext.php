<?php

namespace App\Services\Vouchers;

use App\Models\Member;

class DiscountContext
{
    public function __construct(
        public readonly array   $items,
        public readonly int     $baseSubtotalCents,
        public readonly int     $currentSubtotalCents,
        public readonly int     $currentOfferDiscountCents,
        public readonly array   $appliedDiscounts,
        public readonly ?Member $member,
        public readonly bool    $isSubscription = false,
        public readonly bool    $isFirstSubscriptionCycle = false,
        public readonly ?int    $siteId = null
    )
    {
    }

    public function withUpdatedSubtotal(int $newSubtotalCents, int $additionalDiscountCents): self
    {
        return new self(
            items: $this->items,
            baseSubtotalCents: $this->baseSubtotalCents,
            currentSubtotalCents: $newSubtotalCents,
            currentOfferDiscountCents: $this->currentOfferDiscountCents + $additionalDiscountCents,
            appliedDiscounts: $this->appliedDiscounts,
            member: $this->member,
            isSubscription: $this->isSubscription,
            isFirstSubscriptionCycle: $this->isFirstSubscriptionCycle,
            siteId: $this->siteId
        );
    }

    public function withAppliedDiscount(string $type, array $discountData): self
    {
        $applied = $this->appliedDiscounts;
        $applied[] = ['type' => $type, 'data' => $discountData];

        return new self(
            items: $this->items,
            baseSubtotalCents: $this->baseSubtotalCents,
            currentSubtotalCents: $this->currentSubtotalCents,
            currentOfferDiscountCents: $this->currentOfferDiscountCents,
            appliedDiscounts: $applied,
            member: $this->member,
            isSubscription: $this->isSubscription,
            isFirstSubscriptionCycle: $this->isFirstSubscriptionCycle,
            siteId: $this->siteId
        );
    }
}