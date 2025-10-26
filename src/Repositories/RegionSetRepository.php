<?php

namespace App\Repositories;

use App\Framework\Support\Collection;
use App\Framework\Support\SiteContext;
use App\Models\Model;
use App\Models\Page;
use App\Models\PageRegionSet;
use App\Models\RegionSet;
use App\Search\PaginatedResult;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteria;
use App\Search\SearchEngine;

class RegionSetRepository extends Repository
{
    private SearchEngine $searchEngine;

    public function __construct()
    {
        parent::__construct();
        $config = SearchConfigurationFactory::createRegionSetConfiguration();
        $this->searchEngine = new SearchEngine($config);
    }

    protected function getModelClass(): string
    {
        return RegionSet::class;
    }

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        $query = RegionSet::with(['territories']);
        return $this->searchEngine->search($query, $criteria);
    }

    public function findWithRelations(int $id): ?Model
    {
        return RegionSet::with(['territories'])->find($id);
    }

    public function getActive(): Collection
    {
        $query = RegionSet::ordered()->where('is_active', true);
        return $this->applySiteFilter($query)->get();
    }

    public function checkDeletable(int $regionSetId): array
    {
        $regionSet = $this->find($regionSetId);

        if (!$regionSet) {
            throw new \Exception('Region set not found');
        }

        $territoryCount = $regionSet->getTerritoryCount();
        $pageCount = $regionSet->getPageCount();

        return [
            'can_delete' => $territoryCount === 0 && $pageCount === 0,
            'territory_count' => $territoryCount,
            'page_count' => $pageCount,
            'requires_reassignment' => $territoryCount > 0 || $pageCount > 0
        ];
    }

    public function getAlternatives(int $excludeId): Collection
    {
        $query = RegionSet::ordered()->where('id', '!=', $excludeId);
        return $this->applySiteFilter($query)->get();
    }

    public function reorderRegionSets(array $orderedIds): bool
    {
        $this->database->beginTransaction();

        try {
            foreach ($orderedIds as $index => $id) {
                $regionSet = $this->find($id);

                if(empty($regionSet)) {
                    throw new \Exception('Region set not found');
                }

                if ($regionSet) {
                    $regionSet->sort_order = $index;
                    $regionSet->save();
                }
            }

            $this->database->commit();
            return true;
        } catch (\Exception $e) {
            $this->database->rollBack();
            throw $e;
        }
    }

    public function searchAvailablePages(int $regionSetId, string $query, int $perPage = 20, int $page = 1, ?int $siteId = null): array
    {
        $siteId = $siteId ?? SiteContext::getId();
        // Step 1: Get all page IDs assigned to *other* region sets
        $pageRegionSets = PageRegionSet::where('region_set_id', '!=', $regionSetId)->get();
        $excludedIds = $pageRegionSets->pluck('page_id')
            ->toArray();

        // Step 2: Build main query
        $queryBuilder = Page::query()
            ->when(!empty($excludedIds), function ($query) use ($excludedIds) {
                $query->whereNotIn('id', $excludedIds);
            })
            ->where('title', 'LIKE', "%{$query}%")
            ->where('site_id', $siteId)
            ->orderBy('title');

        // Step 3: Paginate results
        return $queryBuilder->paginate($perPage, $page);
    }

    public function reassignPages(int $oldRegionSetId, int $newRegionSetId): bool
    {
        $pageRegionsets = PageRegionSet::where('region_set_id', $oldRegionSetId)->get();

        foreach ($pageRegionsets as $pageRegionset) {
            $pageRegionset->region_set_id = $newRegionSetId;
            $pageRegionset->save();
        }
        
        return true;
    }
}