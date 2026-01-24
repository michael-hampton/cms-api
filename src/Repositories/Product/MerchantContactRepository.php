<?php

namespace App\Repositories\Product;

use App\Framework\Support\Collection;
use App\Models\MerchantContact;
use App\Repositories\Repository;

class MerchantContactRepository extends Repository
{
    public function getByMerchant(int $merchantId): Collection
    {
        return MerchantContact::where('merchant_id', $merchantId)
            ->orderBy('name')
            ->get();
    }

    public function findByEmail(string $email): ?MerchantContact
    {
        return MerchantContact::where('email', $email)->first();
    }

    protected function getModelClass(): string
    {
        return MerchantContact::class;
    }
}