<?php

namespace App\Database\Seeders;

use App\Models\ProductSpecification;
use App\Models\ProductSpecificationGroup;

class ProductSpecificationGroupSeeder
{
    public function run(): void
    {
        echo "Starting Product Specification Group import...\n";

        // Get all unique categories (case-insensitive)
        $specifications = ProductSpecification::all();
        $categories = [];

        foreach ($specifications as $spec) {
            $category = trim($spec->category ?? 'General');
            $categoryLower = strtolower($category);

            if (!isset($categories[$categoryLower])) {
                // Use first occurrence's capitalization
                $categories[$categoryLower] = ucfirst($category);
            }
        }

        echo "Found " . count($categories) . " unique categories\n";

        // Create specification groups
        $groupMap = [];
        foreach ($categories as $categoryLower => $categoryName) {
            $group = ProductSpecificationGroup::getOrCreate($categoryName);
            $groupMap[$categoryLower] = $group->id;
            echo "Created/found group: {$categoryName} (ID: {$group->id})\n";
        }

        // Backfill specification_group_id
        echo "Backfilling specification_group_id...\n";
        $updated = 0;

        foreach ($specifications as $spec) {
            $category = trim($spec->category ?? 'General');
            $categoryLower = strtolower($category);

            if (isset($groupMap[$categoryLower])) {
                $spec->update([
                    'specification_group_id' => $groupMap[$categoryLower]
                ]);
                $updated++;
            }
        }

        echo "Updated {$updated} specifications with group IDs\n";
        echo "Import complete!\n";
    }
}