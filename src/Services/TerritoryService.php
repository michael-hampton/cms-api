<?php

namespace App\Services;

use App\Exceptions\CannotDeleteException;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Framework\Support\Str;
use App\Models\Page;
use App\Models\Territory;
use App\Repositories\PageRepository;
use App\Repositories\PageTerritoryRepository;
use App\Repositories\TerritoryRepository;

class TerritoryService
{
    public function __construct(
        private readonly Database            $database,
        private readonly TerritoryRepository $repository,
        private PageRepository               $pageRepository,
        private PageTerritoryRepository      $pageTerritoryRepository
    )
    {
    }

    public function create(array $data): Territory
    {
        return $this->database->transaction(function () use ($data) {
            if (empty($data['slug'])) {
                $baseSlug = Str::slug($data['name']);
                $data['slug'] = $this->ensureUniqueSlug($baseSlug);
            }

            return $this->repository->create($data);
        });
    }

    public function update(int $territoryId, array $data): Territory
    {
        return $this->database->transaction(function () use ($territoryId, $data) {
            $territory = $this->repository->find($territoryId);

            if (!$territory) {
                throw new \Exception('Territory not found');
            }

            return $this->repository->update($territoryId, $data);
        });
    }

    private function ensureUniqueSlug(string $baseSlug): string
    {
        $slug = $baseSlug;
        $counter = 1;

        while ($this->repository->slugExists($slug)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public function delete(int $territoryId, ?int $reassignToTerritoryId = null): bool
    {
        $territory = $this->repository->find($territoryId);

        if (!$territory) {
            throw new \Exception('Territory not found');
        }

        $pageCount = $territory->getPageCount();

        if ($pageCount > 0) {
            if ($reassignToTerritoryId === null) {
                throw new CannotDeleteException('territory', $pageCount);
            }

            if ($reassignToTerritoryId === $territoryId) {
                throw new \InvalidArgumentException('Cannot reassign to the same territory being deleted');
            }

            $reassignTerritory = $this->repository->find($reassignToTerritoryId);

            if (!$reassignTerritory) {
                throw new \Exception('Reassignment territory not found');
            }

            return $this->database->transaction(function () use ($territoryId, $territory, $reassignToTerritoryId) {
                // Reassign pages - update pivot table records
                $this->repository->reassignPages($territoryId, $reassignToTerritoryId);

                return $territory->delete();
            });
        }

        return $this->repository->delete($territoryId);
    }

    public function checkDeletable(int $territoryId): array
    {
        return $this->repository->checkDeletable($territoryId);
    }

    public function getAlternativeTerritories(int $territoryId): Collection
    {
        $territory = $this->repository->find($territoryId);

        if (!$territory) {
            throw new \Exception('Territory not found');
        }

        return $this->repository->getAlternativesInRegionSet($territoryId, $territory->region_set_id);
    }

    public function reorder(array $orderedIds): bool
    {
        return $this->repository->reorderTerritories($orderedIds);
    }

    public function bulkUpdateRegionSet(array $territoryIds, int $newRegionSetId): bool
    {
        return $this->repository->bulkUpdateRegionSet($territoryIds, $newRegionSetId);
    }

    public function assignPages(int $territoryId, array $pageIds, int $siteId): bool
    {
        return $this->database->transaction(function () use ($territoryId, $pageIds, $siteId) {
            $this->pageTerritoryRepository->assignPages($territoryId, $pageIds, $siteId);
            return true;
        });
    }

    public function unassignPages(int $territoryId, array $pageIds): bool
    {
        return $this->database->transaction(function () use ($territoryId, $pageIds) {
            $this->pageTerritoryRepository->unassignPages($territoryId, $pageIds);
            return true;
        });
    }

    public function searchAvailablePages(int $territoryId, string $query, int $perPage = 20, int $page = 1): array
    {
        return $this->repository->searchAvailablePages($territoryId, $query, $perPage, $page);
    }
}