<?php

namespace App\Repositories;

use App\Framework\Support\Collection;
use App\Models\Model;
use App\Models\PageGrid;
use App\Models\PageGridHistory;
use App\Models\Product;
use App\Search\PaginatedResult;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteria;
use App\Search\SearchEngine;

class PageGridRepository extends Repository
{
    private SearchEngine $searchEngine;

    public function __construct()
    {
        parent::__construct();
        $config = SearchConfigurationFactory::create('page_grid');
        $this->searchEngine = new SearchEngine($config);
    }

    public function findBySlug(string $slug): ?PageGrid
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('slug', $slug)
            ->with(['creator', 'updater'])
            ->first();
    }

    public function restore(int $id): bool
    {
        $pageGrid = $this->model->withTrashed()->find($id);

        if (!$pageGrid) {
            return false;
        }

        return $pageGrid->restore();
    }

    public function forceDelete(int $id): bool
    {
        $pageGrid = $this->model->withTrashed()->find($id);

        if (!$pageGrid) {
            return false;
        }

        return $pageGrid->forceDelete();
    }

    public function getActive(): Collection
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->with(['creator', 'updater'])
            ->get();
    }

    public function duplicate(int $id): ?Model
    {
        $original = $this->find($id);

        if (!$original) {
            return null;
        }

        $data = $original->toArray();
        unset($data['id'], $data['created_at'], $data['updated_at'], $data['deleted_at']);

        $data['title'] = $data['title'] . ' (Copy)';
        $data['slug'] = $data['slug'] . '-copy';

        return $this->create($data);
    }

    protected function getModelClass(): string
    {
        return PageGrid::class;
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $query = PageGrid::where('slug', $slug);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        $query = PageGrid::with(['territories']);
        return $this->searchEngine->search($query, $criteria);
    }

    public function logHistory(int $pageGridId, string $action, ?int $userId = null, array $changes = []): void
    {
        PageGridHistory::create([
            'page_grid_id' => $pageGridId,
            'user_id' => $userId,
            'action' => $action,
            'changes' => json_encode($changes),
        ]);
    }

    public function getHistory(int $pageGridId): Collection
    {
        $this->withoutSiteFilter();

        return PageGridHistory::where('page_grid_id', $pageGridId)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}