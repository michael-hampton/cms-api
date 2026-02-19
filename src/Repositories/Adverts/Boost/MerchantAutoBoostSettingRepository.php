<?php

namespace App\Repositories\Adverts\Boost;

use App\Models\MerchantAutoBoostSetting;

class MerchantAutoBoostSettingRepository
{
    public function findByMerchant(int $merchantId): ?MerchantAutoBoostSetting
    {
        return MerchantAutoBoostSetting::where('merchant_id', $merchantId)->first();
    }

    public function upsert(int $merchantId, array $data): MerchantAutoBoostSetting
    {
        $setting = MerchantAutoBoostSetting::firstOrNew(['merchant_id' => $merchantId]);
        $setting->fill($data);
        $setting->save();
        return $setting;
    }

    public function getEnabledSettings(): \App\Framework\Support\Collection
    {
        return MerchantAutoBoostSetting::where('is_enabled', true)->get();
    }

    public function incrementBudgetUsed(int $merchantId, float $amount): void
    {
        MerchantAutoBoostSetting::where('merchant_id', $merchantId)
            ->increment('budget_used_this_month', $amount);
    }
}