<?php

namespace App\Repositories\Subscriptions;

use App\Models\Site;

final class SubscriptionAccountSiteRepository
{
    /**
     * @param array<int,int> $siteIds
     * @return array<int,Site>
     */
    public function findByIdsIndexed(array $siteIds): array
    {
        $siteIds = array_values(array_unique(array_filter(array_map('intval', $siteIds))));

        if ($siteIds === []) {
            return [];
        }

        $sites = [];

        foreach (Site::whereIn('id', $siteIds)->get() as $site) {
            $sites[(int) $site->id] = $site;
        }

        return $sites;
    }
}
