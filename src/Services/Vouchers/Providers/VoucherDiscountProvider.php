<?php

namespace App\Services\Vouchers\Providers;

use App\Services\Vouchers\Contracts\DiscountProvider;
use App\Services\Vouchers\DiscountApplicationResult;
use App\Services\Vouchers\DiscountContext;

class VoucherDiscountProvider implements DiscountProvider
{
    public function __construct(
        private readonly ?array $voucherData = null
    )
    {
    }

    public function priority(): int
    {
        return 30; // After offers and tiered, before rewards
    }

    public function apply(DiscountContext $context): ?DiscountApplicationResult
    {
        if (!$this->supports($context)) {
            return null;
        }

        $eligibleSubtotalCents = $this->calculateEligibleSubtotal($context);

        if ($eligibleSubtotalCents === 0) {
            return null;
        }

        $discountCents = $this->calculateDiscountCents($eligibleSubtotalCents);

        // Apply max discount cap if set
        if (isset($this->voucherData['max_discount'])) {
            $maxDiscountCents = (int)round($this->voucherData['max_discount'] * 100);
            $discountCents = min($discountCents, $maxDiscountCents);
        }

        // Determine funding source
        $fundingSource = 'platform'; // Default
        if (!empty($this->voucherData['merchant_id'])) {
            $fundingSource = 'merchant';
        }

        $affectedItemIds = array_map(
            fn($item) => $item['id'] ?? $item['product_id'],
            $this->voucherData['eligible_items'] ?? []
        );

        return new DiscountApplicationResult(
            discountAmountCents: $discountCents,
            affectedItemIds: $affectedItemIds,
            stackable: $this->voucherData['is_stackable'] ?? false,
            fundingSource: $fundingSource,
            type: 'voucher',
            metadata: [
                'voucher_id' => $this->voucherData['voucher_id'] ?? null,
                'voucher_code' => $this->voucherData['voucher_code'] ?? null,
                'campaign_id' => $this->voucherData['campaign_id'] ?? null,
                'merchant_id' => $this->voucherData['merchant_id'] ?? null,
                'discount_type' => $this->voucherData['discount_type'] ?? 'percentage'
            ]
        );
    }

    public function supports(DiscountContext $context): bool
    {
        if ($this->voucherData === null || empty($this->voucherData['valid'])) {
            return false;
        }

        // Check subscription compatibility
        if ($context->isSubscription) {
            return $this->isApplicableToSubscription($context);
        }

        return true;
    }

    private function isApplicableToSubscription(DiscountContext $context): bool
    {
        // Check voucher applies_to field
        $appliesTo = $this->voucherData['applies_to'] ?? 'one_time';

        if ($appliesTo === 'one_time') {
            return false;
        }

        if ($appliesTo === 'subscription_first_cycle' && !$context->isFirstSubscriptionCycle) {
            return false;
        }

        return true;
    }

    private function calculateEligibleSubtotal(DiscountContext $context): int
    {
        $eligibleCents = 0;

        $eligibleItemIds = array_map(
            fn($item) => $item['id'] ?? $item['product_id'],
            $this->voucherData['eligible_items'] ?? []
        );

        foreach ($context->items as $item) {
            $itemId = $item['id'] ?? $item['product_id'];

            if (in_array($itemId, $eligibleItemIds)) {
                $priceCents = (int)round($item['price'] * 100);
                $quantity = $item['quantity'] ?? 1;
                $eligibleCents += $priceCents * $quantity;
            }
        }

        return $eligibleCents;
    }

    private function calculateDiscountCents(int $eligibleSubtotalCents): int
    {
        $discountType = $this->voucherData['discount_type'] ?? 'percentage';

        if ($discountType === 'percentage') {
            $percentage = $this->voucherData['discount'] ?? 0;
            return (int)round($eligibleSubtotalCents * ($percentage / 100));
        } else {
            // Fixed amount
            $fixedAmountCents = (int)round($this->voucherData['discount'] * 100);
            return min($fixedAmountCents, $eligibleSubtotalCents);
        }
    }
}