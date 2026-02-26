<?php

namespace App\Services\Vouchers;

use App\Services\Vouchers\DiscountContext\DiscountContext;

class DiscountResolver
{
    /**
     * @param DiscountProviderRegistry $registry
     */
    public function __construct(
        private readonly DiscountProviderRegistry $registry
    )
    {
    }

    public function resolve(DiscountContext $context): ResolvedDiscounts
    {
        // Sort providers by priority
        $sortedProviders = $this->registry->sortedByPriority();

        $currentContext = $context;
        $offerDiscount = 0;
        $tieredDiscount = 0;
        $voucherDiscount = 0;
        $rewardDiscount = 0;
        $storeCredit = 0;
        $merchantFunded = 0;
        $platformFunded = 0;
        $customerCredit = 0;
        $metadata = [];

        foreach ($sortedProviders as $provider) {
            if (!$provider->supports($currentContext)) {
                continue;
            }

            $result = $provider->apply($currentContext);

            if ($result === null || $result->discountAmountCents === 0) {
                continue;
            }

            // Handle non-stackable discounts
            if (!$result->stackable && $currentContext->currentOfferDiscountCents > 0) {
                // Non-stackable discount overrides previous discounts
                // Reset all discount counters and recalculate
                $offerDiscount = 0;
                $tieredDiscount = 0;
                $voucherDiscount = 0;
                $rewardDiscount = 0;

                $currentContext = $context->resetForNonStackable();
            }

            // Track discount by type
            switch ($result->type) {
                case 'offer':
                    $offerDiscount += $result->discountAmountCents;
                    break;
                case 'tiered':
                    $tieredDiscount += $result->discountAmountCents;
                    break;
                case 'voucher':
                    $voucherDiscount += $result->discountAmountCents;
                    break;
                case 'reward':
                    $rewardDiscount += $result->discountAmountCents;
                    break;
                case 'store_credit':
                    $storeCredit += $result->discountAmountCents;
                    break;
            }

            // Track funding
            switch ($result->fundingSource) {
                case 'merchant':
                    $merchantFunded += $result->discountAmountCents;
                    break;
                case 'platform':
                    $platformFunded += $result->discountAmountCents;
                    break;
                case 'customer_credit':
                    $customerCredit += $result->discountAmountCents;
                    break;
                case 'mixed':
                    // Split 50/50 for simplicity, can be refined
                    $merchantFunded += (int)($result->discountAmountCents / 2);
                    $platformFunded += (int)($result->discountAmountCents / 2);
                    break;
            }

            // Store metadata
            if (!empty($result->metadata)) {
                $metadata[$result->type] = $result->metadata;
            }

            // Update context for next provider
            $newSubtotal = $currentContext->currentSubtotalCents - $result->discountAmountCents;

            $currentContext = $currentContext->withUpdatedSubtotal(
                $newSubtotal,
                $result->discountAmountCents
            );
            $currentContext = $currentContext->withAppliedDiscount($result->type, $result->metadata);
        }

        return new ResolvedDiscounts(
            items: $context->items,
            baseSubtotalCents: $context->baseSubtotalCents,
            finalSubtotalCents: max(0, $currentContext->currentSubtotalCents),
            offerDiscountCents: $offerDiscount,
            tieredDiscountCents: $tieredDiscount,
            voucherDiscountCents: $voucherDiscount,
            rewardDiscountCents: $rewardDiscount,
            storeCreditCents: $storeCredit,
            merchantFundedCents: $merchantFunded,
            platformFundedCents: $platformFunded,
            customerCreditCents: $customerCredit,
            metadata: $metadata
        );
    }
}