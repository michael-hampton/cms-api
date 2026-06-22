<?php

namespace App\Repositories\PublicContent;

use App\Framework\Support\Collection;
use App\Models\Voucher;

final class PublicVoucherRepository
{
    public function activeForSite(int $siteId, int $limit = 8): Collection
    {
        $now = date('Y-m-d H:i:s');

        return Voucher::where('site_id', $siteId)
            ->where('status', 'active')
            ->where(function ($query) use ($now) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', $now);
            })
            ->where(function ($query) {
                $query->whereNull('usage_limit')
                    ->orWhereColumn('usage_count', '<', 'usage_limit');
            })
            ->orderBy('expires_at', 'asc')
            ->limit($limit)
            ->get();
    }
}
