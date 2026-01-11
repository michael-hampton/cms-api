<?php

namespace App\Actions;

use App\Framework\Database\Database;
use App\Framework\Support\Str;
use App\Repositories\Cms\RegionSetRepository;
use App\Repositories\Cms\TerritoryRepository;

class CloneRegionSet
{
    private Database $database;

    public function __construct(
        Database                                 $database,
        private readonly RegionSetRepository     $repository,
        private readonly TerritoryRepository     $territoryRepository,
    ) {
        $this->database = $database ?? Database::getInstance();
    }

    public function handle(int $regionSetId, ?string $newName = null): array
    {
        return $this->database->transaction(function () use ($regionSetId, $newName) {
            $results = [
                'success' => [],
                'failed' => [],
                'territories_cloned' => 0
            ];

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

            try {
                $newRegionSet = $this->repository->create($data);
                $results['success'][] = 'region_set_created';
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'operation' => 'create_region_set',
                    'error' => $e->getMessage()
                ];
                throw $e;
            }

            // Duplicate territories
            $territories = $originalRegionSet->territories;

            if ($territories) {
                foreach ($territories as $territory) {
                    try {
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

                        $results['territories_cloned']++;
                    } catch (\Exception $e) {
                        $results['failed'][] = [
                            'operation' => 'clone_territory',
                            'territory_id' => $territory->id,
                            'territory_name' => $territory->name,
                            'error' => $e->getMessage()
                        ];
                    }
                }

                if ($results['territories_cloned'] > 0) {
                    $results['success'][] = 'territories_cloned';
                }
            }

            // Add clone history
            try {
                $originalRegionSet->addCloneRecord('cloned_to', $newRegionSet->id, null);
                $newRegionSet->addCloneRecord('cloned_from', $originalRegionSet->id, null);
                $results['success'][] = 'clone_history';
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'operation' => 'clone_history',
                    'error' => $e->getMessage()
                ];
            }

            return [
                'region_set' => $newRegionSet,
                'results' => $results,
                'original_region_set_id' => $regionSetId
            ];
        });
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
}