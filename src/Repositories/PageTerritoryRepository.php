<?php

namespace App\Repositories;

use App\Framework\Support\Collection;
use App\Models\PageTerritory;

class PageTerritoryRepository extends Repository
{
    protected function getModelClass(): string
    {
        return PageTerritory::class;
    }

    public function syncTerritories(int $pageId, array $territoryIds, int $siteId): void
    {
        // Delete existing associations
        PageTerritory::where('page_id', $pageId)->delete();

        // Create new associations
        foreach ($territoryIds as $territoryId) {
            PageTerritory::create([
                'page_id' => $pageId,
                'territory_id' => $territoryId,
                'site_id' => $siteId
            ]);
        }
    }

    public function getTerritoriesForPage(int $pageId): Collection
    {
        return PageTerritory::where('page_id', $pageId)
            ->with(['territory'])
            ->get();
    }

    public function assignPages(int $territoryId, array $pageIds, int $siteId): void
    {
        foreach ($pageIds as $pageId) {
            $exists = PageTerritory::where('page_id', $pageId)
                ->where('territory_id', $territoryId)
                ->first();

            if (!$exists) {
                PageTerritory::create([
                    'page_id' => $pageId,
                    'territory_id' => $territoryId,
                    'site_id' => $siteId
                ]);
            }
        }
    }

    public function unassignPages(int $territoryId, array $pageIds): void
    {
        PageTerritory::where('territory_id', $territoryId)
            ->whereIn('page_id', $pageIds)
            ->delete();
    }
}