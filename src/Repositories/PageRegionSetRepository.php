<?php

namespace App\Repositories;

use App\Framework\Support\Collection;
use App\Models\PageRegionSet;

class PageRegionSetRepository extends Repository
{
    protected function getModelClass(): string
    {
        return PageRegionSet::class;
    }

    public function syncRegionSets(int $pageId, array $regionSetIds, int $siteId): void
    {
        // Delete existing associations
        PageRegionSet::where('page_id', $pageId)->delete();

        // Create new associations
        foreach ($regionSetIds as $regionSetId) {
            PageRegionSet::create([
                'page_id' => $pageId,
                'region_set_id' => $regionSetId,
                'site_id' => $siteId
            ]);
        }
    }

    public function getRegionSetsForPage(int $pageId): Collection
    {
        return PageRegionSet::where('page_id', $pageId)
            ->with(['regionSet'])
            ->get();
    }

    public function assignPages(int $regionSetId, array $pageIds, int $siteId): void
    {
        foreach ($pageIds as $pageId) {
            // Check if assignment already exists
            $exists = PageRegionSet::where('page_id', $pageId)
                ->where('region_set_id', $regionSetId)
                ->first();

            if (!$exists) {
                PageRegionSet::create([
                    'page_id' => $pageId,
                    'region_set_id' => $regionSetId,
                    'site_id' => $siteId
                ]);
            }
        }
    }

    public function unassignPages(int $regionSetId, array $pageIds): int
    {
        return PageRegionSet::where('region_set_id', $regionSetId)
            ->whereIn('page_id', $pageIds)
            ->delete();
    }
}