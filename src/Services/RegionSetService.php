<?php

namespace App\Services;

use App\Exceptions\CannotDeleteException;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Framework\Support\Str;
use App\Models\Page;
use App\Models\RegionSet;
use App\Repositories\PageRegionSetRepository;
use App\Repositories\PageRepository;
use App\Repositories\RegionSetRepository;
use App\Repositories\TerritoryRepository;

class RegionSetService
{
    private Database $database;
    protected RegionSetRepository $repository;
    protected TerritoryRepository $territoryRepository;

    public function __construct(
        Database $database,
        RegionSetRepository $repository,
        TerritoryRepository $territoryRepository,
        private readonly PageRepository $pageRepository,
        private readonly PageRegionSetRepository $pageRegionSetRepository
    ) {
        $this->database = $database ?? Database::getInstance();
        $this->repository = $repository;
        $this->territoryRepository = $territoryRepository;
    }

    public function create(array $data): RegionSet
    {
        return $this->database->transaction(function () use ($data) {
            // Generate slug if not provided
            if (empty($data['slug']) && !empty($data['name'])) {
                $data['slug'] = $this->generateUniqueSlug($data['name']);
            }

            $regionSet = $this->repository->create($data);

            // Create territories if provided
            if (!empty($data['territories']) && is_array($data['territories'])) {
                $this->syncTerritories($regionSet->id, $data['territories'], $data['site_id']);
            }

            return $regionSet;
        });
    }

    public function update(int $regionSetId, array $data): RegionSet
    {
        return $this->database->transaction(function () use ($regionSetId, $data) {
            $regionSet = $this->repository->find($regionSetId);

            if (!$regionSet) {
                throw new \Exception('Region set not found');
            }

            // Update slug if name changed
            if (!empty($data['name']) && $data['name'] !== $regionSet->name) {
                if (empty($data['slug'])) {
                    $data['slug'] = $this->generateUniqueSlug($data['name'], $regionSetId);
                }
            }

            $regionSet = $this->repository->update($regionSetId, $data);

            // Update territories if provided
            if (isset($data['territories']) && is_array($data['territories'])) {
                $this->syncTerritories($regionSetId, $data['territories'], $data['site_id']);
            }

            return $regionSet;
        });
    }

    public function delete(int $regionSetId, ?int $reassignToRegionSetId = null): bool
    {
        $regionSet = $this->repository->find($regionSetId);

        if (!$regionSet) {
            throw new \Exception('Region set not found');
        }

        $territoryCount = $regionSet->getTerritoryCount();
        $pageCount = $regionSet->getPageCount();

        if ($territoryCount > 0 || $pageCount > 0) {
            if ($reassignToRegionSetId === null) {
                throw new CannotDeleteException('region set', $territoryCount + $pageCount);
            }

            if ($reassignToRegionSetId === $regionSetId) {
                throw new \InvalidArgumentException('Cannot reassign to the same region set being deleted');
            }

            $reassignRegionSet = $this->repository->find($reassignToRegionSetId);

            if (!$reassignRegionSet) {
                throw new \Exception('Reassignment region set not found');
            }

            return $this->database->transaction(function () use ($regionSetId, $regionSet, $reassignToRegionSetId) {
                // Reassign territories
                $territories = $this->territoryRepository->getByRegionSet($regionSetId);
                foreach ($territories as $territory) {
                    $territory->region_set_id = $reassignToRegionSetId;
                    $territory->save();
                }

                // Reassign pages
                $this->repository->reassignPages($regionSetId, $reassignToRegionSetId);

                return $regionSet->delete();
            });
        }

        return $this->repository->delete($regionSetId);
    }

    public function checkDeletable(int $regionSetId): array
    {
        return $this->repository->checkDeletable($regionSetId);
    }

    public function getAlternativeRegionSets(int $regionSetId): Collection
    {
        return $this->repository->getAlternatives($regionSetId);
    }

    public function duplicate(int $regionSetId, ?string $newName = null): RegionSet
    {
        return $this->database->transaction(function () use ($regionSetId, $newName) {
            $originalRegionSet = $this->repository->findWithRelations($regionSetId);

            if (!$originalRegionSet) {
                throw new \Exception("Region set not found");
            }

            $data = [
                'name' => $newName ?? ($originalRegionSet->name . ' (Copy)'),
                'description' => $originalRegionSet->description,
                'is_active' => false,
                'site_id' => $originalRegionSet->site_id
            ];

            $data['slug'] = $this->generateUniqueSlug($data['name']);
            $newRegionSet = $this->repository->create($data);


            // Duplicate territories
            $territories = $originalRegionSet->territories;

            if ($territories) {
                foreach ($territories as $territory) {
                    // Generate unique code by checking if it exists
                    $newCode = $territory->code . '-copy';
                    $counter = 1;

                    while ($this->territoryRepository->findByCode($newCode, $territory->site_id)) {
                        $newCode = $territory->code . '-copy-' . $counter;
                        $counter++;
                    }

                    $newSlug = $this->territoryRepository->generateUniqueSlug($territory->name, $territory->site_id);

                    $this->territoryRepository->create([
                        'name' => $territory->name,
                        'slug' => $newSlug,
                        'code' => $newCode,
                        'region_set_id' => $newRegionSet->id,
                        'is_active' => $territory->is_active,
                        'sort_order' => $territory->sort_order,
                        'site_id' => $territory->site_id
                    ]);
                }
            }

            return $newRegionSet;
        });
    }

    public function reorder(array $orderedIds): bool
    {
        return $this->repository->reorderRegionSets($orderedIds);
    }

    protected function syncTerritories(int $regionSetId, array $territories, int $siteId): void
    {
        // Get existing territory IDs
        $existingTerritories = $this->territoryRepository->getByRegionSet($regionSetId);
        $existingIds = $existingTerritories->pluck('id')->toArray();

        $providedIds = array_filter(array_column($territories, 'id'));

        // Delete territories not in the provided list
        $toDelete = array_diff($existingIds, $providedIds);
        foreach ($toDelete as $id) {
            $this->territoryRepository->delete($id);
        }

        // Update or create territories
        foreach ($territories as $index => $territoryData) {
            $territoryData['region_set_id'] = $regionSetId;
            $territoryData['site_id'] = $siteId;
            $territoryData['sort_order'] = $index;

            if (!empty($territoryData['id'])) {
                $this->territoryRepository->update($territoryData['id'], $territoryData);
            } else {
                $this->territoryRepository->create($territoryData);
            }
        }
    }

    protected function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $slug = Str::slug($name);
        $counter = 1;

        while (true) {
            $existing = $this->repository->findBySlug($slug);

            if (!$existing || ($excludeId && $existing->id === $excludeId)) {
                break;
            }

            $slug = Str::slug($name . '-' . $counter);
            $counter++;
        }

        return $slug;
    }

    public function assignPages(int $regionSetId, array $pageIds, int $siteId): bool
    {
        return $this->database->transaction(function () use ($regionSetId, $pageIds, $siteId) {
            $this->pageRegionSetRepository->assignPages($regionSetId, $pageIds, $siteId);
            return true;
        });
    }

    public function unassignPages(int $regionSetId, array $pageIds): bool
    {
        return $this->database->transaction(function () use ($regionSetId, $pageIds) {
            $this->pageRegionSetRepository->unassignPages($regionSetId, $pageIds);
            return true;
        });
    }

    public function searchAvailablePages(int $regionSetId, string $query, int $perPage = 20, int $page = 1): array
    {
        return $this->repository->searchAvailablePages($regionSetId, $query, $perPage, $page);
    }

    public function bulkDelete(array $regionSetIds): array
    {
        return $this->database->transaction(function() use ($regionSetIds) {
            $deleted = [];
            $failed = [];

            foreach ($regionSetIds as $regionSetId) {
                try {
                    $regionSet = $this->repository->find($regionSetId);

                    if (!$regionSet) {
                        $failed[] = ['id' => $regionSetId, 'reason' => 'Region set not found'];
                        continue;
                    }

                    $territoryCount = $regionSet->getTerritoryCount();
                    $pageCount = $regionSet->getPageCount();

                    if ($territoryCount > 0 || $pageCount > 0) {
                        $failed[] = [
                            'id' => $regionSetId,
                            'reason' => "Region set has {$territoryCount} territories and {$pageCount} pages"
                        ];
                        continue;
                    }

                    if ($this->repository->delete($regionSetId)) {
                        $deleted[] = $regionSetId;
                    } else {
                        $failed[] = ['id' => $regionSetId, 'reason' => 'Delete failed'];
                    }
                } catch (\Exception $e) {
                    $failed[] = ['id' => $regionSetId, 'reason' => $e->getMessage()];
                }
            }

            return [
                'deleted' => $deleted,
                'failed' => $failed,
                'total' => count($regionSetIds)
            ];
        });
    }

    public function bulkActivate(array $regionSetIds): array
    {
        return $this->bulkUpdateActiveStatus($regionSetIds, true);
    }

    public function bulkDeactivate(array $regionSetIds): array
    {
        return $this->bulkUpdateActiveStatus($regionSetIds, false);
    }

    private function bulkUpdateActiveStatus(array $regionSetIds, bool $isActive): array
    {
        return $this->database->transaction(function() use ($regionSetIds, $isActive) {
            $updated = [];
            $failed = [];

            foreach ($regionSetIds as $regionSetId) {
                try {
                    $regionSet = $this->repository->find($regionSetId);

                    if (!$regionSet) {
                        $failed[] = ['id' => $regionSetId, 'reason' => 'Region set not found'];
                        continue;
                    }

                    $updatedRegionSet = $this->repository->update($regionSetId, ['is_active' => $isActive]);

                    if ($updatedRegionSet) {
                        $updated[] = $regionSetId;
                    } else {
                        $failed[] = ['id' => $regionSetId, 'reason' => 'Update failed'];
                    }
                } catch (\Exception $e) {
                    $failed[] = ['id' => $regionSetId, 'reason' => $e->getMessage()];
                }
            }

            return [
                'updated' => $updated,
                'failed' => $failed,
                'total' => count($regionSetIds)
            ];
        });
    }
}