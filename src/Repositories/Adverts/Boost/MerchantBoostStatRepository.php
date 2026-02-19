<?php

namespace App\Repositories\Adverts\Boost;

use App\Models\MerchantBoostStat;
use App\Repositories\Repository;

class MerchantBoostStatRepository extends Repository
{
    public function findByMerchant(int $merchantId): ?MerchantBoostStat
    {
        return MerchantBoostStat::where('merchant_id', $merchantId)->first();
    }

    public function upsert(int $merchantId, array $data): MerchantBoostStat
    {
        $stat = MerchantBoostStat::firstOrNew(['merchant_id' => $merchantId]);
        $stat->fill($data);
        $stat->save();

        return $stat;
    }

    protected function getModelClass(): string
    {
        return MerchantBoostStat::class;
    }
}