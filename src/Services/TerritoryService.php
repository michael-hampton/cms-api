<?php

namespace App\Services;

use App\Exceptions\CannotDeleteException;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Framework\Support\Str;
use App\Models\Page;
use App\Models\Territory;
use App\Repositories\PageRepository;
use App\Repositories\TerritoryRepository;

class TerritoryService
{
    private Database $database;
    protected TerritoryRepository $repository;

    public function __construct(
        Database $database,
        TerritoryRepository $repository,
        private PageRepository $pageRepository
    )
    {
        $this->database = $database ?? Database::getInstance();
        $this->repository = $repository;
    }

    public function create(array $data): Territory
    {
        return $this->database->transaction(function () use ($data) {
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
                // Reassign pages
                $pages = $territory->pages();
                foreach ($pages as $page) {
                    $page->territory_id = $reassignToTerritoryId;
                    $page->save();
                }

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

    public function assignPages(int $territoryId, array $pageIds): bool
    {
        return $this->database->transaction(function () use ($territoryId, $pageIds) {
            foreach ($pageIds as $pageId) {
                $page = $this->pageRepository->find($pageId);
                if ($page) {
                    $page->territory_id = $territoryId;
                    $page->save();
                }
            }
            return true;
        });
    }

    public function unassignPages(int $territoryId, array $pageIds): bool
    {
        return $this->database->transaction(function () use ($territoryId, $pageIds) {
            foreach ($pageIds as $pageId) {
                $page = $this->pageRepository->find($pageId);
                if ($page && $page->territory_id == $territoryId) {
                    $page->territory_id = null;
                    $page->save();
                }
            }
            return true;
        });
    }

    public function searchAvailablePages(int $territoryId, string $query, int $perPage = 20, int $page = 1): array
    {
        return $this->repository->searchAvailablePages($territoryId, $query, $perPage, $page);
    }
}