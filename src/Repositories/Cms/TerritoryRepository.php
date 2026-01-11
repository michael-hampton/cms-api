<?php

namespace App\Repositories\Cms;

use App\Framework\Support\Collection;
use App\Framework\Support\SiteContext;
use App\Framework\Support\Str;
use App\Models\Model;
use App\Models\Page;
use App\Models\PageTerritory;
use App\Models\Territory;
use App\Repositories\Repository;
use App\Search\PaginatedResult;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteria;
use App\Search\SearchEngine;

class TerritoryRepository extends Repository
{
    private SearchEngine $searchEngine;

    public function __construct()
    {
        parent::__construct();
        $config = SearchConfigurationFactory::create('territory');
        $this->searchEngine = new SearchEngine($config);
    }

    protected function getModelClass(): string
    {
        return Territory::class;
    }

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        $query = Territory::with(['regionSet'])->withCount(['pages']);
        return $this->searchEngine->search($query, $criteria);
    }

    public function findWithRelations(int $id): ?Model
    {
        return Territory::with(['regionSet'])->find($id);
    }

    public function getByRegionSet(int $regionSetId): Collection
    {
        $query = Territory::ordered()->where('region_set_id', $regionSetId);;
        return $this->applySiteFilter($query)->get();
    }

    public function getActive(): Collection
    {
        $query = Territory::ordered()->where('is_active', true);
        return $this->applySiteFilter($query)->get();
    }

    public function findByCode(string $code): ?Model
    {
        $query = Territory::where('code', $code);
        return $this->applySiteFilter($query)->first();
    }

    public function checkDeletable(int $territoryId, ?int $siteId = null): array
    {
        $siteId = $siteId ?? SiteContext::getId();
        $territory = Territory::where('id', $territoryId)->where('site_id', $siteId)->first();


        if (!$territory) {
            throw new \Exception('Territory not found');
        }

        $pageCount = $territory->getPageCount();

        return [
            'can_delete' => $pageCount === 0,
            'page_count' => $pageCount,
            'requires_reassignment' => $pageCount > 0
        ];
    }

    public function getAlternativesInRegionSet(int $territoryId, int $regionSetId): Collection
    {
        $query = Territory::ordered()->where('id', '!=', $territoryId)
             ->where(function($q) use ($regionSetId) {
                 $q->whereNull('region_set_id')
                     ->orWhere('region_set_id', $regionSetId);
             });
        return $this->applySiteFilter($query)->get();
    }

    public function reorderTerritories(array $orderedIds): bool
    {
        $this->database->beginTransaction();

        try {
            foreach ($orderedIds as $index => $id) {
                $territory = $this->find($id);
                if ($territory) {
                    $territory->sort_order = $index;
                    $territory->save();
                }
            }

            $this->database->commit();
            return true;
        } catch (\Exception $e) {
            $this->database->rollBack();
            throw $e;
        }
    }

    public function bulkUpdateRegionSet(array $territoryIds, int $newRegionSetId): bool
    {
        $this->database->beginTransaction();

        try {
            foreach ($territoryIds as $id) {
                $territory = $this->find($id);
                if ($territory) {
                    $territory->region_set_id = $newRegionSetId;
                    $territory->save();
                }
            }

            $this->database->commit();
            return true;
        } catch (\Exception $e) {
            $this->database->rollBack();
            throw $e;
        }
    }

    public function searchAvailablePages(int $territoryId, string $query, int $perPage = 20, int $page = 1, ?int $siteId = null): array
    {
        $siteId = $siteId ?? SiteContext::getId();
        $pageTerritories = PageTerritory::where('territory_id', '!=', $territoryId)->get();
        $excludedIds = $pageTerritories->pluck('page_id')
            ->toArray();

        $queryBuilder = Page::query()
            ->when(!empty($excludedIds), function ($query) use ($excludedIds) {
                // Only apply whereNotIn if we have IDs to exclude
                $query->whereNotIn('id', $excludedIds);
            })
            ->where('title', 'LIKE', "%{$query}%")
            ->where('site_id', $siteId)
            ->orderBy('title');

        return $queryBuilder->paginate($perPage, $page);
    }

    public function generateUniqueSlug(string $name, int $siteId, ?int $excludeId = null): string
    {
        $slug = Str::slug($name);
        $counter = 1;

        while (true) {
            $query = Territory::where('slug', $slug)
                ->where('site_id', $siteId);

            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }

            $existing = $query->first();

            if (!$existing) {
                break;
            }

            $slug = Str::slug($name) . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public function slugExists(string $slug): bool
    {
        return Territory::where('slug', $slug)->exists();
    }

    public function reassignPages(int $oldTerritoryId, int $newTerritoryId): bool
    {
        return PageTerritory::where('territory_id', $oldTerritoryId)
            ->update(['territory_id' => $newTerritoryId]);
    }
}