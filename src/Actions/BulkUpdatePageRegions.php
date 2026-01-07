<?php

namespace App\Actions;

use App\Repositories\PageRegionSetRepository;
use App\Repositories\PageRepository;
use App\Repositories\PageTerritoryRepository;

class BulkUpdatePageRegions
{
    public function __construct(
        private readonly PageRepository          $pageRepository,
        private readonly PageRegionSetRepository $pageRegionSetRepository,
        private readonly PageTerritoryRepository $pageTerritoryRepository
    )
    {
    }

    public function handle(array $pageIds, array $regionSetIds, array $territoryIds): array
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
                    $this->pageRegionSetRepository->syncRegionSets($pageId, $regionSetIds);
                }

                // Sync territories
                if (!empty($territoryIds)) {
                    $this->pageTerritoryRepository->syncTerritories($pageId, $territoryIds);
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