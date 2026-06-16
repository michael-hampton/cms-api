<?php

namespace App\Repositories\PublicContent;

use App\Framework\Support\Collection;
use App\Models\Badge;
use App\Repositories\Repository;

final class PublicBadgeRepository extends Repository
{
    public function getActiveEngagementBadges(int $siteId): Collection
    {
        return Badge::where('site_id', $siteId)
            ->where('is_active', true)
            ->where('category', 'engagement')
            ->get();
    }

    protected function getModelClass(): string
    {
        return Badge::class;
    }
}
