<?php

namespace App\Actions;

use App\Framework\Database\Database;
use App\Framework\Support\Str;
use App\Models\RegionSet;
use App\Repositories\RegionSetRepository;
use App\Repositories\TerritoryRepository;

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

    public function handle(int $regionSetId, ?string $newName = null): RegionSet
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

            // Add clone history
            $originalRegionSet->addCloneRecord('cloned_to', $newRegionSet->id, null);
            $newRegionSet->addCloneRecord('cloned_from', $originalRegionSet->id, null);

            return $newRegionSet;
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