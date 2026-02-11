<?php

namespace App\Services\Vouchers;

use App\DTO\VoucherValidationResult;
use App\Exceptions\CannotDeleteException;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\Concerns\HasCloneHistory;
use App\Models\Voucher;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Vouchers\VoucherRepository;

class VoucherService
{
    use HasCloneHistory;

    public function __construct(
        private readonly Database                   $database,
        private readonly VoucherRepository          $repository,
        private readonly SubscriptionPlanRepository $subscriptionPlanRepository
    )
    {
    }

    public function create(array $data): Voucher
    {
        $productIds = $data['product_ids'] ?? [];
        $categoryIds = $data['category_ids'] ?? [];
        $brandIds = $data['brand_ids'] ?? [];

        unset($data['product_ids'], $data['category_ids'], $data['brand_ids']);

        $voucher = $this->repository->create($data);

        if (!empty($productIds)) {
            $this->repository->syncProducts($voucher->id, $productIds);
        }

        if (!empty($categoryIds)) {
            $this->repository->syncCategories($voucher->id, $categoryIds);
        }

        if (!empty($brandIds)) {
            $this->repository->syncBrands($voucher->id, $brandIds);
        }

        return $voucher;
    }

    public function update(int $voucherId, array $data): ?Voucher
    {
        $productIds = $data['product_ids'] ?? null;
        $categoryIds = $data['category_ids'] ?? null;
        $brandIds = $data['brand_ids'] ?? null;

        unset($data['product_ids'], $data['category_ids'], $data['brand_ids']);

        $voucher = $this->repository->update($voucherId, $data);

        if ($productIds !== null) {
            $this->repository->syncProducts($voucherId, $productIds);
        }

        if ($categoryIds !== null) {
            $this->repository->syncCategories($voucherId, $categoryIds);
        }

        if ($brandIds !== null) {
            $this->repository->syncBrands($voucherId, $brandIds);
        }

        return $voucher;
    }

    public function delete(int $voucherId): bool
    {
        $voucher = $this->repository->find($voucherId);

        if (!$voucher) {
            throw new \Exception('Voucher not found');
        }

        if ($voucher->usage_count > 0) {
            throw new CannotDeleteException('voucher', $voucher->usage_count);
        }

        return $this->repository->delete($voucherId);
    }

    public function checkDeletable(int $voucherId): array
    {
        return $this->repository->checkDeletable($voucherId);
    }

    public function getAlternativeVouchers(int $voucherId): Collection
    {
        return $this->repository->getAlternatives($voucherId);
    }

    public function validateVoucherForCheckout($code, $cartItems, $userId = null)
    {
        $voucher = $this->repository->findByCode($code);

        if (!$voucher) {
            return [
                'valid' => false,
                'message' => 'Voucher not found',
                'discount' => 0
            ];
        }

        if (!$voucher->isValid()) {
            return [
                'valid' => false,
                'message' => 'Voucher has expired',
                'discount' => 0
            ];
        }

        // Check campaign status if voucher is part of a campaign
        if ($voucher->campaign_id) {
            $campaign = $voucher->campaign;
            if (!$campaign || $campaign->status !== 'active' || !$campaign->isActive()) {
                return [
                    'valid' => false,
                    'message' => 'Campaign is not active',
                    'discount' => 0
                ];
            }
        }

        // Check per-user limit
        if ($voucher->per_user_limit && $userId) {
            $usageCount = $voucher->getUserUsageCount($userId);
            if ($usageCount >= $voucher->per_user_limit) {
                return [
                    'valid' => false,
                    'message' => 'You have already used this voucher the maximum number of times',
                    'discount' => 0
                ];
            }
        }

        // Get eligible items (products and subscription plans)
        $eligibleItems = $this->getEligibleItems($voucher, $cartItems);

        if (empty($eligibleItems)) {
            return [
                'valid' => false,
                'message' => 'Voucher is not applicable to any items in your cart',
                'discount' => 0,
                'eligible_items' => []
            ];
        }

        // Calculate eligible subtotal
        $eligibleSubtotal = array_sum(array_column($eligibleItems, 'subtotal'));

        // Check minimum order value
        if ($voucher->minimum_order_value && $eligibleSubtotal < $voucher->minimum_order_value) {
            return [
                'valid' => false,
                'message' => "Minimum order value of £{$voucher->minimum_order_value} required for eligible items",
                'discount' => 0
            ];
        }

        // Calculate discount
        $discount = $voucher->calculateDiscount($eligibleSubtotal);

        // Check if cart has offer discounts
        $hasOfferDiscount = $this->cartHasOfferDiscount($cartItems);

        // FIXED: Don't reject non-stackable vouchers - let CheckoutService decide
        // Return constraint information for CheckoutService to handle
        return [
            'valid' => true,
            'discount' => $discount,
            'discount_type' => $voucher->discount_type,
            'max_discount' => $voucher->max_discount_amount,
            'voucher_id' => $voucher->id,
            'voucher_code' => $voucher->code,
            'campaign_id' => $voucher->campaign_id,
            'merchant_id' => $voucher->merchant_id,
            'eligible_items' => $eligibleItems,
            'eligible_subtotal' => $eligibleSubtotal,
            'is_stackable' => $voucher->is_stackable,
            'has_offer_discount' => $hasOfferDiscount,
            'requires_override_decision' => $hasOfferDiscount && !$voucher->is_stackable,
            'message' => 'Voucher validated successfully'
        ];
    }

    private function getEligibleItems($voucher, $cartItems)
    {
        $eligible = [];

        foreach ($cartItems as $item) {
            $isEligible = false;

            // Check product eligibility
            if (isset($item['product_id']) && $voucher->isApplicableToProduct($item['product_id'])) {
                $isEligible = true;
            }

            // Check subscription plan eligibility
            if (isset($item['subscription_plan_id']) && $voucher->isApplicableToSubscriptionPlan($item['subscription_plan_id'])) {
                $isEligible = true;
            }

            if ($isEligible) {
                $eligible[] = $item;
            }
        }

        return $eligible;
    }

    private function cartHasOfferDiscount($cartItems)
    {
        foreach ($cartItems as $item) {
            // Check if item has offer or bundle pricing
            if (isset($item['item_type']) && in_array($item['item_type'], ['offer', 'bundle'])) {
                return true;
            }

            // Check if sale_price exists and differs from base price
            if (isset($item['price']) && isset($item['base_price']) && $item['price'] < $item['base_price']) {
                return true;
            }
        }

        return false;
    }

    public function validateVoucher(
        string $code,
        float  $orderValue,
        ?int   $userId = null,
        ?int   $productId = null
    ): array
    {
        $voucher = $this->repository->findByCode($code);

        if (!$voucher) {
            return [
                'valid' => false,
                'message' => 'Voucher not found',
                'discount' => 0
            ];
        }

        if (!$voucher->isValid()) {
            $message = 'Voucher is not valid';

            if ($voucher->status === 'expired') {
                $message = 'Voucher has expired';
            } elseif ($voucher->status === 'inactive') {
                $message = 'Voucher is inactive';
            } elseif ($voucher->usage_limit && $voucher->usage_count >= $voucher->usage_limit) {
                $message = 'Voucher usage limit reached';
            }

            return [
                'valid' => false,
                'message' => $message,
                'discount' => 0
            ];
        }

        // Check per-user limit
        if ($userId && $voucher->per_user_limit) {
            $userUsageCount = $voucher->getUserUsageCount($userId);

            if ($userUsageCount >= $voucher->per_user_limit) {
                return [
                    'valid' => false,
                    'message' => 'You have already used this voucher the maximum number of times',
                    'discount' => 0
                ];
            }
        }

        // Check if voucher applies to specific product
        if ($productId && !$voucher->isApplicableToProduct($productId)) {
            return [
                'valid' => false,
                'message' => 'Voucher not applicable to this product',
                'discount' => 0
            ];
        }

        if (!empty($orderValue) && $voucher->minimum_order_value && $orderValue < $voucher->minimum_order_value) {
            return [
                'valid' => false,
                'message' => "Minimum order value of {$voucher->minimum_order_value} required",
                'discount' => 0
            ];
        }

        $discount = $voucher->calculateDiscount($orderValue);

        return [
            'valid' => true,
            'message' => 'Voucher applied successfully',
            'discount' => $discount,
            'voucher_id' => $voucher->id,
            'voucher' => $voucher,
            'is_stackable' => $voucher->is_stackable
        ];
    }

    public function applyVoucher(int $voucherId, ?int $userId = null, float $discountAmount = 0, ?int $orderId = null): bool
    {
        $success = $this->repository->incrementUsageCount($voucherId);

        if ($success && $discountAmount > 0) {
            $this->repository->createRedemption($voucherId, $userId, $discountAmount, $orderId);
        }

        return $success;
    }

    public function updateExpiredVouchers(): int
    {
        return $this->repository->updateExpiredVouchers();
    }

    public function validateVoucherForSubscription(
        string $code,
        int    $planId,
        ?int   $userId = null
    ): VoucherValidationResult
    {
        $voucher = $this->repository->findByCode($code);

        if (!$voucher) {
            return VoucherValidationResult::invalid('Voucher not found');

        }

        if (!$voucher->isValid()) {
            $message = $this->getInvalidMessage($voucher);
            return VoucherValidationResult::invalid($message);
        }

        if (!$voucher->appliesToSubscriptions()) {
            return VoucherValidationResult::invalid('This voucher cannot be used for subscriptions');
        }

        if (!$voucher->isApplicableToSubscriptionPlan($planId)) {
            return VoucherValidationResult::invalid('Voucher not applicable to this subscription plan');
        }

        // Check per-user limit
        if ($userId && $voucher->per_user_limit) {
            $userUsageCount = $voucher->getUserUsageCount($userId);

            if ($userUsageCount >= $voucher->per_user_limit) {
                return VoucherValidationResult::invalid(
                    'You have already used this voucher the maximum number of times'
                );
            }
        }

        $plan = $this->subscriptionPlanRepository->find($planId);

        if (!$plan) {
            return VoucherValidationResult::invalid('Plan not found');
        }

        $discount = $voucher->calculateSubscriptionDiscount($plan->price);

        $finalPrice = max(0, $plan->price - $discount);

        return VoucherValidationResult::valid($voucher, $discount, $finalPrice);
    }

    private function getInvalidMessage($voucher): string
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

    public function getVoucherById($voucher_id)
    {
        return $this->repository->find($voucher_id);
    }
}