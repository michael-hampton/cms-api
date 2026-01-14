<?php

namespace App\Repositories\Product;

use App\Framework\Support\Collection;
use App\Models\ProductSpecification;
use App\Models\ProductSpecificationGroup;
use App\Repositories\Repository;

class ProductSpecificationGroupRepository extends Repository
{
    /**
     * Get all active specification groups with counts
     */
    public function getAllWithCounts(int $siteId): Collection
    {
        $groups = ProductSpecificationGroup::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $groups->map(function ($group) use ($siteId) {
            // Get specifications for this group from active products on this site
            $specifications = ProductSpecification::where('specification_group_id', $group->id)
                ->whereHas('product', function ($query) use ($siteId) {
                    $query->where('site_id', $siteId)
                        ->where('is_active', true);
                })
                ->get();

            // Count unique products
            $productCount = $specifications->pluck('product_id')->unique()->count();

            return [
                'id' => $group->id,
                'name' => $group->name,
                'slug' => $group->slug,
                'product_count' => $productCount,
                'specifications' => $this->getSpecificationValuesForGroup($specifications)
            ];
        });
    }

    /**
     * Get unique specification values for a group
     */
    protected function getSpecificationValuesForGroup(Collection $specifications): array
    {
        $valuesByKey = [];

        foreach ($specifications as $spec) {
            $key = $spec->key;

            if (!isset($valuesByKey[$key])) {
                $valuesByKey[$key] = [
                    'values' => [],
                    'count' => 0
                ];
            }

            $value = $spec->value;
            if (!in_array($value, $valuesByKey[$key]['values'])) {
                $valuesByKey[$key]['values'][] = $value;
            }

            $valuesByKey[$key]['count']++;
        }

        $result = [];
        foreach ($valuesByKey as $key => $data) {
            $result[] = [
                'key' => $key,
                'values' => $data['values'],
                'count' => $data['count']
            ];
        }

        return $result;
    }

    /**
     * Find or create group by name
     */
    public function findOrCreateByName(string $name): ProductSpecificationGroup
    {
        return ProductSpecificationGroup::getOrCreate($name);
    }

    protected function getModelClass(): string
    {
        return ProductSpecificationGroup::class;
    }
}