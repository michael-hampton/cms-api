<?php

namespace App\Services\Vouchers;

use App\DTO\Vouchers\VoucherValidationContext;
use App\DTO\Vouchers\VoucherValidationResult;
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
        });
    }

    public function update(int $voucherId, array $data): ?Voucher
    {
        return $this->database->transaction(function () use ($voucherId, $data) {
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

        $context = $productId
            ? VoucherValidationContext::forProduct($productId, $orderValue, $userId, false)
            : VoucherValidationContext::forProduct(0, $orderValue, $userId, false);

        $result = $this->validationService->validate($voucher, $context);

        return [
            'valid' => $result->valid,
            'message' => $result->message,
            'discount' => $result->discount,
            'voucher_id' => $result->voucher?->id,
            'voucher' => $result->voucher,
            'is_stackable' => $result->isStackable
        ];
    }

    public function validateVoucherForCheckout(string $code, array $cartItems, ?int $userId = null): array
    {
        $voucher = $this->repository->findByCode($code);

        if (!$voucher) {
            return [
                'valid' => false,
                'message' => 'Voucher not found',
                'discount' => 0
            ];
        }

        $hasOfferDiscount = $this->cartHasOfferDiscount($cartItems);
        $context = VoucherValidationContext::forCheckout($cartItems, $userId, $hasOfferDiscount);

        $result = $this->validationService->validate($voucher, $context);

        return $result->toArray();
    }

    public function validateVoucherForSubscription(
        string $code,
        int  $planId,
        ?int $userId = null
    ): VoucherValidationResult
    {
        $voucher = $this->repository->findByCode($code);

        if (!$voucher) {
            return VoucherValidationResult::invalid('Voucher not found');
        }

        $plan = $this->subscriptionPlanRepository->find($planId);

        if (!$plan) {
            return VoucherValidationResult::invalid('Plan not found');
        }

        $context = VoucherValidationContext::forSubscription($planId, $plan->price, $userId);

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
}