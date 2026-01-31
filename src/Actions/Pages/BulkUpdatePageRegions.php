<?php

namespace App\Actions\Pages;

use App\Repositories\Cms\Pages\PageRegionSetRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\Pages\PageTerritoryRepository;

class BulkUpdatePageRegions
{
    public function __construct(
        private readonly PageRepository          $pageRepository,
        private readonly PageRegionSetRepository $pageRegionSetRepository,
        private readonly PageTerritoryRepository $pageTerritoryRepository
    )
    {
    }

    public function handle(array $pageIds, array $regionSetIds, array $territoryIds, int $siteId): array
    {
        $results = [];

        foreach ($pageIds as $pageId) {
            try {
                $page = $this->pageRepository->find($pageId);

                if (!$page) {
                    $results[$pageId] = [
                        'success' => false,
                        'error' => 'Page not found'
                    ];
                    continue;
                }

                // Sync region sets
                if (!empty($regionSetIds)) {
                    $this->pageRegionSetRepository->syncRegionSets($pageId, $regionSetIds, $siteId);
                }

                // Sync territories
                if (!empty($territoryIds)) {
                    $this->pageTerritoryRepository->syncTerritories($pageId, $territoryIds, $siteId);
                }

                $results[$pageId] = ['success' => true];
            } catch (\Exception $e) {
                $results[$pageId] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }
}