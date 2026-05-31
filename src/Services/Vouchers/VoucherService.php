<?php

namespace App\Services\Vouchers;

use App\DTO\Vouchers\VoucherValidationContext;
use App\DTO\Vouchers\VoucherValidationResult;
use App\Enums\Subscriptions\SubscriptionType;
use App\Exceptions\Vouchers\VoucherNotDeletableException;
use App\Exceptions\Vouchers\VoucherNotFoundException;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\Voucher;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Vouchers\VoucherRepository;

class VoucherService
{
    public function __construct(
        private readonly Database                   $database,
        private readonly VoucherRepository          $repository,
        private readonly SubscriptionPlanRepository $subscriptionPlanRepository,
        private readonly VoucherValidationService   $validationService
    )
    {
    }

    public function create(array $data): Voucher
    {
        return $this->database->transaction(function () use ($data) {
            $productIds = $data['product_ids'] ?? [];
            $categoryIds = $data['category_ids'] ?? [];
            $brandIds = $data['brand_ids'] ?? [];
            $subscriptionPlanIds = $data['subscription_plan_ids'] ?? [];

            unset($data['product_ids'], $data['category_ids'], $data['brand_ids'], $data['subscription_plan_ids']);

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

            if (!empty($subscriptionPlanIds)) {
                $this->repository->syncSubscriptionPlans($voucher->id, $subscriptionPlanIds);
            }

            return $voucher;
        });
    }

    public function update(int $voucherId, array $data): ?Voucher
    {
        return $this->database->transaction(function () use ($voucherId, $data) {
            $productIds = $data['product_ids'] ?? null;
            $categoryIds = $data['category_ids'] ?? null;
            $brandIds = $data['brand_ids'] ?? null;
            $subscriptionPlanIds = $data['subscription_plan_ids'] ?? null;

            if ($this->mayAffectSubscriptionCouponConfiguration($data)) {
                $existingVoucher = $this->repository->find($voucherId);

                if ($existingVoucher && $this->subscriptionCouponConfigurationChanged($existingVoucher, $data)) {
                    $data['stripe_coupon_id'] = null;
                    $data['stripe_coupon_synced_at'] = null;
                }
            }

            unset($data['product_ids'], $data['category_ids'], $data['brand_ids'], $data['subscription_plan_ids']);

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

            if ($subscriptionPlanIds !== null) {
                $this->repository->syncSubscriptionPlans($voucherId, $subscriptionPlanIds);
            }

            return $voucher;
        });
    }

    public function delete(int $voucherId): bool
    {
        return $this->database->transaction(function () use ($voucherId) {
            $voucher = $this->repository->find($voucherId);

            if (!$voucher) {
                throw new VoucherNotFoundException("ID {$voucherId}");
            }

            if ($voucher->usage_count > 0) {
                throw new VoucherNotDeletableException($voucher->usage_count);
            }

            return $this->repository->delete($voucherId);
        });
    }

    public function checkDeletable(int $voucherId): array
    {
        return $this->repository->checkDeletable($voucherId);
    }

    public function getAlternativeVouchers(int $voucherId): Collection
    {
        return $this->repository->getAlternatives($voucherId);
    }

    public function validateVoucher(
        string $code,
        float $orderValue,
        ?int  $userId = null,
        ?int  $productId = null
    ): VoucherValidationResult
    {
        $voucher = $this->repository->findByCode($code);

        if (!$voucher) {
            return VoucherValidationResult::invalid('Voucher not found');
        }

        $context = $productId
            ? VoucherValidationContext::forProduct($productId, $orderValue, $userId, false)
            : VoucherValidationContext::forProduct(0, $orderValue, $userId, false);

        $result = $this->validationService->validate($voucher, $context);

        return $result;
    }

    public function validateVoucherForCheckout(string $code, array $cartItems, ?int $userId = null): VoucherValidationResult
    {
        $voucher = $this->repository->findByCode($code);

        if (!$voucher) {
            return VoucherValidationResult::invalid('Voucher not found');
        }

        $hasOfferDiscount = $this->cartHasOfferDiscount($cartItems);
        $context = VoucherValidationContext::forCheckout($cartItems, $userId, $hasOfferDiscount);

        return $this->validationService->validate($voucher, $context);
    }

    public function validateVoucherForSubscription(
        string  $code,
        int     $planId,
        ?int    $userId = null,
        ?int    $pricingTierId = null,
        ?string $deliveryType = null
    ): VoucherValidationResult
    {
        $voucher = $this->repository->findByCode($code);

        if (!$voucher) {
            return VoucherValidationResult::invalid('Voucher not found');
        }

        $plan = $this->subscriptionPlanRepository->find($planId, ['pricingTiers']);

        if (!$plan) {
            return VoucherValidationResult::invalid('Plan not found');
        }

        $price = $plan->price;

        if ($pricingTierId !== null) {

            $pricingTier = $plan->pricingTiers->firstWhere('id', $pricingTierId);

            if (!$pricingTier) {
                return VoucherValidationResult::invalid('Invalid pricing tier');
            }

            if ($deliveryType === SubscriptionType::DIGITAL->value) {
                $price = min(
                    $pricingTier->digital_price,
                    $pricingTier->digital_sale_price ?? $pricingTier->digital_price
                );
            } else {
                $price = min(
                    $pricingTier->price,
                    $pricingTier->sale_price ?? $pricingTier->price
                );
            }
        }

        $context = VoucherValidationContext::forSubscription($planId, $price, $userId);

        return $this->validationService->validate($voucher, $context);
    }

    public function applyVoucher(
        int   $voucherId,
        ?int  $userId = null,
        float $discountAmount = 0,
        ?int  $orderId = null
    ): bool
    {
        return $this->database->transaction(function () use ($voucherId, $userId, $discountAmount, $orderId) {
            $success = $this->repository->incrementUsageCount($voucherId);

            if ($success && $discountAmount > 0) {
                $this->repository->createRedemption($voucherId, $userId, $discountAmount, $orderId);
            }

            return $success;
        });
    }

    public function updateExpiredVouchers(): int
    {
        return $this->repository->updateExpiredVouchers();
    }

    public function getVoucherById(int $voucherId): ?Voucher
    {
        return $this->repository->find($voucherId);
    }

    private function cartHasOfferDiscount(array $cartItems): bool
    {
        foreach ($cartItems as $item) {
            if (isset($item['item_type']) && in_array($item['item_type'], ['offer', 'bundle'])) {
                return true;
            }

            if (isset($item['price']) && isset($item['base_price']) && $item['price'] < $item['base_price']) {
                return true;
            }
        }

        return false;
    }

    private function subscriptionCouponConfigurationChanged(Voucher $voucher, array $newData): bool
    {
        if (empty($voucher->stripe_coupon_id)) {
            return false;
        }

        $updated = clone $voucher;

        foreach ($newData as $key => $value) {
            $updated->{$key} = $value;
        }

        return $voucher->subscriptionCouponConfiguration() !== $updated->subscriptionCouponConfiguration();
    }

    private function mayAffectSubscriptionCouponConfiguration(array $data): bool
    {
        foreach ([
            'type',
            'value',
            'discount_type',
            'discount_amount',
            'discount_percentage',
            'applies_to_subscriptions',
            'subscription_discount_duration',
            'subscription_duration_months',
            'duration_in_months',
        ] as $key) {
            if (array_key_exists($key, $data)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find an active promotion voucher automatically attached to a plan.
     * Called when no user-entered voucher code was provided at checkout.
     * Returns null if the plan has no active promotion.
     */
    public function findActivePromotionForPlan(int $planId): ?Voucher
    {
        return $this->repository->findActivePromotionForPlan($planId);
    }
}
