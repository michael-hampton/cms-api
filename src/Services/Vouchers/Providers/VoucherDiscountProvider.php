<?php

namespace App\Services\Vouchers\Providers;

use App\Services\Vouchers\Contracts\DiscountProvider;
use App\Services\Vouchers\DiscountApplicationResult;
use App\Services\Vouchers\DiscountContext\DiscountContext;
use App\Services\Vouchers\VoucherService;

final class VoucherDiscountProvider implements DiscountProvider
{
    public function __construct(
        private readonly VoucherService $voucherService
    )
    {
    }

    public function priority(): int
    {
        return 30; // After offers and tiered, before rewards
    }

    public function supports(DiscountContext $context): bool
    {
        $voucherContext = $context->voucherContext;

        if ($voucherContext === null || empty($voucherContext->voucherData['voucher_code'])) {
            return false;
        }

        return true;
    }

    public function apply(DiscountContext $context): ?DiscountApplicationResult
    {
        $voucherContext = $context->voucherContext;

        if (!$voucherContext) {
            return null;
        }

        // Validate voucher (again if needed; supports() could cache result if desired)
        $validationResult = $voucherContext->voucherData['applies_to'] === 'subscription_first_cycle' ?
            $this->voucherService->validateVoucherForSubscription(
                $voucherContext->voucherData['voucher_code'],
                $voucherContext->voucherData['subscription_plan_id'], null,
                $voucherContext->voucherData['pricing_tier_id'] ?? null,
                $voucherContext->voucherData['delivery_type'] ?? null
            ) : $this->voucherService->validateVoucher($voucherContext->voucherData['voucher_code'], $voucherContext->voucherData['order_value']);

        if (!$validationResult->valid || $validationResult->eligibleSubtotal === 0) {
            return null;
        }

        $eligibleSubtotalCents = $validationResult->eligibleSubtotal;
        $discountCents = $this->calculateDiscountCents($context, $eligibleSubtotalCents) ?: $validationResult->discount * 100;

        if ($discountCents <= 0) {
            return null;
        }

        // Apply max discount cap if defined
        $maxDiscount = $voucherContext->voucherData['max_discount'] ?? null;
        if ($maxDiscount !== null) {
            $discountCents = min($discountCents, (int)round($maxDiscount * 100));
        }

        $fundingSource = !empty($voucherContext->voucherData['merchant_id']) ? 'merchant' : 'platform';

        return new DiscountApplicationResult(
            discountAmountCents: $discountCents,
            affectedItemIds: $validationResult->eligibleItems,
            stackable: $voucherContext->voucherData['is_stackable'] ?? false,
            fundingSource: $fundingSource,
            type: 'voucher',
            metadata: [
                'voucher_id' => $voucherContext->voucherData['voucher_id'] ?? null,
                'voucher_code' => $voucherContext->voucherData['voucher_code'] ?? null,
                'campaign_id' => $voucherContext->voucherData['campaign_id'] ?? null,
                'merchant_id' => $voucherContext->voucherData['merchant_id'] ?? null,
                'discount_type' => $voucherContext->voucherData['discount_type'] ?? 'percentage',
            ]
        );
    }

    /**
     * Calculate the discount in cents based on eligible subtotal and voucher type
     */
    private function calculateDiscountCents(DiscountContext $context, int $eligibleSubtotalCents): int
    {
        $voucherData = $context->voucherContext->voucherData;
        $discountType = $voucherData['discount_type'] ?? 'percentage';

        if ($discountType === 'percentage') {
            $percentage = $voucherData['discount'] ?? 0;
            return (int)round($eligibleSubtotalCents * ($percentage / 100));
        }

        // fixed amount discount
        $fixedAmountCents = (int)round($voucherData['discount'] * 100);
        return min($fixedAmountCents, $eligibleSubtotalCents);
    }
}
