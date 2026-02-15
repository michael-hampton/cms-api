<?php

namespace App\Services\Vouchers;

use App\DTO\Vouchers\VoucherValidationContext;
use App\DTO\Vouchers\VoucherValidationResult;
use App\Models\Voucher;
use App\Repositories\Vouchers\VoucherRepository;

class VoucherValidationService
{
    public function __construct(
        private readonly VoucherRepository          $repository,
        private readonly VoucherEligibilityResolver $eligibilityResolver
    )
    {
    }

    public function validate(
        Voucher                  $voucher,
        VoucherValidationContext $context
    ): VoucherValidationResult
    {
        // Step 1: Check voucher validity
        if (!$voucher->isValid()) {
            return VoucherValidationResult::invalid($this->getInvalidMessage($voucher));
        }

        // Step 2: Check campaign status
        if ($voucher->campaign_id) {
            $campaign = $voucher->campaign;
            if (!$campaign || $campaign->status !== 'active' || !$campaign->isActive()) {
                return VoucherValidationResult::invalid('Campaign is not active');
            }
        }

        // Step 3: Check per-user usage limits
        if ($voucher->per_user_limit && $context->userId) {
            $usageCount = $voucher->getUserUsageCount($context->userId);
            if ($usageCount >= $voucher->per_user_limit) {
                return VoucherValidationResult::invalid(
                    'You have already used this voucher the maximum number of times'
                );
            }
        }

        // Step 4: Handle subscription-specific validation
        if ($context->subscriptionPlanId) {
            return $this->validateSubscription($voucher, $context);
        }

        // Step 5: Handle product-specific validation
        if ($context->productId) {
            return $this->validateProduct($voucher, $context);
        }

        // Step 6: Handle cart validation
        return $this->validateCart($voucher, $context);
    }

    private function validateSubscription(
        Voucher                  $voucher,
        VoucherValidationContext $context
    ): VoucherValidationResult
    {
        if (!$voucher->appliesToSubscriptions()) {
            return VoucherValidationResult::invalid('This voucher cannot be used for subscriptions');
        }

        if (!$voucher->isApplicableToSubscriptionPlan($context->subscriptionPlanId)) {
            return VoucherValidationResult::invalid('Voucher not applicable to this subscription plan');
        }

        $discount = $voucher->calculateSubscriptionDiscount($context->orderValue);

        return VoucherValidationResult::valid(
            voucher: $voucher,
            discount: $discount,
            finalPrice: max(0, $context->orderValue - $discount)
        );
    }

    private function validateProduct(
        Voucher                  $voucher,
        VoucherValidationContext $context
    ): VoucherValidationResult
    {
        if (!$voucher->isApplicableToProduct($context->productId)) {
            return VoucherValidationResult::invalid('Voucher not applicable to this product');
        }

        if ($voucher->minimum_order_value && $context->orderValue < $voucher->minimum_order_value) {
            return VoucherValidationResult::invalid(
                "Minimum order value of £{$voucher->minimum_order_value} required"
            );
        }

        $discount = $voucher->calculateDiscount($context->orderValue);

        return VoucherValidationResult::valid(
            voucher: $voucher,
            discount: $discount,
            eligibleSubtotal: $context->orderValue,
            isStackable: $voucher->is_stackable ?? true
        );
    }

    private function validateCart(
        Voucher                  $voucher,
        VoucherValidationContext $context
    ): VoucherValidationResult
    {
        // Get eligible items
        $eligibleItems = $this->eligibilityResolver->resolveEligibleItems($voucher, $context->cartItems);

        if (empty($eligibleItems) && $context->forCart === true) {
            return VoucherValidationResult::invalid(
                'Voucher is not applicable to any items in your cart'
            );
        }

        // Calculate eligible subtotal
        $eligibleSubtotal = $context->forCart === true ? array_sum(array_column($eligibleItems, 'subtotal')) : $context->orderValue;

        // Check minimum order value
        if ($voucher->minimum_order_value && $eligibleSubtotal < $voucher->minimum_order_value) {
            return VoucherValidationResult::invalid(
                "Minimum order value of £{$voucher->minimum_order_value} required for eligible items"
            );
        }

        // Calculate discount
        $discount = $voucher->calculateDiscount($eligibleSubtotal);

        // Return with constraint information
        return VoucherValidationResult::valid(
            voucher: $voucher,
            discount: $discount,
            finalPrice: max(0, $eligibleSubtotal - $discount),
            eligibleSubtotal: $eligibleSubtotal,
            eligibleItems: $eligibleItems,
            isStackable: !$voucher->isNonStackable(),
            requiresOverrideDecision: $voucher->requiresOverrideForOfferDiscount(
                $context->hasOfferDiscount
            )
        );
    }

    private function getInvalidMessage(Voucher $voucher): string
    {
        if ($voucher->status === 'expired') {
            return 'Voucher has expired';
        } elseif ($voucher->status === 'inactive') {
            return 'Voucher is inactive';
        } elseif ($voucher->usage_limit && $voucher->usage_count >= $voucher->usage_limit) {
            return 'Voucher usage limit reached';
        }

        return 'Voucher is not valid';
    }
}