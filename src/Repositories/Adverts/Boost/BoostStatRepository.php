<?php

namespace App\Repositories\Adverts\Boost;

use App\Models\BoostStat;
use App\Repositories\Repository;

class BoostStatRepository extends Repository
{
    public function findByBoost(int $boostId): ?BoostStat
    {
        return BoostStat::where('boost_id', $boostId)->first();
    }

    public function upsert(int $boostId, array $data): BoostStat
    {
        $stat = BoostStat::firstOrNew(['boost_id' => $boostId]);

        $stat->fill($data);
        $stat->save();

        return $stat;
    }

    public function sumByMerchant(int $merchantId): array
    {
        $result = BoostStat::join('boosts', 'boost_stats.boost_id', '=', 'boosts.id')
            ->where('boosts.merchant_id', $merchantId)
            ->selectRaw('
            SUM(boost_stats.impressions)      as impressions,
            SUM(boost_stats.clicks)           as clicks,
            SUM(boost_stats.conversions)      as conversions,
            SUM(boost_stats.spend_attributed) as spend_attributed
        ')
            ->first();

        return [
            'impressions' => (int)($result->impressions ?? 0),
            'clicks' => (int)($result->clicks ?? 0),
            'conversions' => (int)($result->conversions ?? 0),
            'spend_attributed' => (float)($result->spend_attributed ?? 0.0),
        ];
    }

    protected function getModelClass(): string
    {
        return BoostStat::class;
    }
}