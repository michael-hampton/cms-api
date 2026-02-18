<?php

namespace App\Repositories\Product;

use App\Models\ProductMerchant;
use App\Repositories\Repository;

class MerchantProductRepository extends Repository
{
    public function existsForMerchant(int $productId, int $merchantId): bool
    {
        return ProductMerchant::where('product_id', $productId)
            ->where('merchant_id', $merchantId)
            ->exists();
    }

    public function findByNameAndMerchant(string $name, int $merchantId): ?ProductMerchant
    {
        return ProductMerchant::whereHas('product', function ($q) use ($name) {
            $q->where('name', $name);
        })
            ->where('merchant_id', $merchantId)
            ->first();
    }

    protected function getModelClass(): string
    {
        return ProductMerchant::class;
    }
}