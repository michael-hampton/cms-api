<?php

namespace App\Repositories\Product;

use App\Models\Model;
use App\Models\PriceAlert;
use App\Models\Product;
use App\Models\ProductMerchant;
use App\Models\ProductVariant;

class PriceAlertRepository
{
    public function findActiveAlertByEmailAndProduct(string $email, int $productId): ?PriceAlert
    {
        return PriceAlert::where('email', $email)
            ->where('product_id', $productId)
            ->where('is_triggered', false)
            ->first();
    }

    public function create(array $data): PriceAlert
    {
        return PriceAlert::create($data);
    }

    public function update(PriceAlert $alert, array $data): bool
    {
        return $alert->update($data);
    }

    public function getUntriggeredAlerts(): array
    {
        return PriceAlert::where('is_triggered', false)
            ->where('is_notified', false)
            ->get()
            ->toArray();
    }

    public function getUserAlerts(int $userId): array
    {
        return PriceAlert::where('user_id', $userId)
            ->with(['product'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    public function findById(int $alertId, ?int $userId = null): ?PriceAlert
    {
        $query = PriceAlert::where('id', $alertId);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->first();
    }

    public function delete(PriceAlert $alert): bool
    {
        return $alert->delete();
    }

    public function getTotalCount(): int
    {
        return PriceAlert::count();
    }

    public function getActiveCount(): int
    {
        return PriceAlert::where('is_triggered', false)->count();
    }

    public function getTriggeredCount(): int
    {
        return PriceAlert::where('is_triggered', true)
            ->where('is_notified', false)
            ->count();
    }

    public function getNotifiedCount(): int
    {
        return PriceAlert::where('is_notified', true)->count();
    }

    public function findVariant(int $variantId): ?Model
    {
        return ProductVariant::find($variantId);
    }

    public function findMerchantForProduct(int $merchantId, int $productId): ?Model
    {
        return ProductMerchant::where('product_id', $productId)
            ->where('merchant_id', $merchantId)
            ->whereNull('variant_id')
            ->first();
    }

    public function findMerchantForVariant(int $merchantId, int $variantId): ?Model
    {
        return ProductMerchant::where('variantId', $variantId)
            ->where('merchant_id', $merchantId)
            ->first();
    }

    public function getProductWithVariantMerchant(int $productId): ?Model
    {
        return Product::with(['variants.merchants', 'merchants'])->find($productId);
    }
}