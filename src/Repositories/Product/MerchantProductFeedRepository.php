<?php

namespace App\Repositories\Product;

use App\Framework\Support\Collection;
use App\Models\MerchantProductFeed;
use App\Repositories\Repository;

class MerchantProductFeedRepository extends Repository
{
    public function getByMerchant(int $merchantId): Collection
    {
        return MerchantProductFeed::where('merchant_id', $merchantId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getActiveFeedsByMerchant(int $merchantId): Collection
    {
        return MerchantProductFeed::where('merchant_id', $merchantId)
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getDueForFetch(): Collection
    {
        return MerchantProductFeed::dueForFetch()->get();
    }

    public function getByStatus(string $status): Collection
    {
        return MerchantProductFeed::where('status', $status)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    protected function getModelClass(): string
    {
        return MerchantProductFeed::class;
    }
}