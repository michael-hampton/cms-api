<?php

namespace App\Services\Product;

use App\Repositories\Product\ProductRepository;

class ProductComparisonService
{
    public function __construct(
        private readonly ProductRepository $productRepository,
    )
    {
    }

    /**
     * Compare 2-4 products based on shared specifications
     *
     * @param array $productIds Array of 2-4 product IDs
     * @return array Structured comparison data
     */
    public function compareProducts(array $productIds): array
    {
        // Validate product count
        if (count($productIds) < 2 || count($productIds) > 4) {
            throw new \InvalidArgumentException('Must compare between 2 and 4 products');
        }

        // Load products with specifications
        $products = [];
        foreach ($productIds as $id) {
            $product = $this->productRepository->find($id, ['specifications', 'specifications.specificationGroup']);
            if (!$product) {
                throw new \InvalidArgumentException("Product {$id} not found");
            }
            $products[] = $product;
        }

        // Find products that share at least one specification group
        $comparableProducts = $this->getComparableProducts($products);

        if (count($comparableProducts) < 2) {
            return [
                'comparable' => false,
                'reason' => 'At least 2 products must share specification groups',
                'excluded_products' => $this->getExcludedProductInfo($products, $comparableProducts)
            ];
        }

        // Get only shared specifications among comparable products
        $sharedSpecs = $this->getSharedSpecifications($comparableProducts);

        // Group by specification groups
        $groupedSpecs = $this->groupSpecifications($sharedSpecs);

        // Identify differences
        $differences = $this->identifyDifferences($groupedSpecs, $comparableProducts);

        return [
            'comparable' => true,
            'products' => $this->formatProducts($comparableProducts),
            'specification_groups' => $groupedSpecs,
            'differences' => $differences,
            'ai_summary' => $this->generateAISummary($comparableProducts, $differences),
            'excluded_products' => count($comparableProducts) < count($products)
                ? $this->getExcludedProductInfo($products, $comparableProducts)
                : []
        ];
    }

    /**
     * Get products that share at least one specification group with at least one other product
     */
    private function getComparableProducts(array $products): array
    {
        $productGroups = [];

        // Build map of products to their specification groups
        foreach ($products as $index => $product) {
            $groups = [];
            foreach ($product->specifications as $spec) {
                if ($spec->specification_group_id) {
                    $groups[] = $spec->specification_group_id;
                }
            }
            $productGroups[$index] = array_unique($groups);
        }

        // Find which products share groups
        $comparable = [];
        foreach ($products as $index => $product) {
            $sharesWithAnother = false;

            foreach ($products as $otherIndex => $otherProduct) {
                if ($index === $otherIndex) continue;

                $intersection = array_intersect($productGroups[$index], $productGroups[$otherIndex]);
                if (count($intersection) > 0) {
                    $sharesWithAnother = true;
                    break;
                }
            }

            if ($sharesWithAnother) {
                $comparable[] = $product;
            }
        }

        return $comparable;
    }

    /**
     * Get info about excluded products
     */
    private function getExcludedProductInfo(array $allProducts, array $comparableProducts): array
    {
        $comparableIds = array_map(fn($p) => $p->id, $comparableProducts);
        $excluded = [];

        foreach ($allProducts as $product) {
            if (!in_array($product->id, $comparableIds)) {
                $excluded[] = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'reason' => 'No shared specification groups with other products'
                ];
            }
        }

        return $excluded;
    }

    /**
     * Get only specifications that exist on ALL products
     */
    private function getSharedSpecifications(array $products): array
    {
        // Build a map of spec keys per product
        $productSpecKeys = [];
        foreach ($products as $index => $product) {
            $keys = [];
            foreach ($product->specifications as $spec) {
                $keys[] = $spec->key;
            }
            $productSpecKeys[$index] = array_unique($keys);
        }

        // Find intersection - keys that exist on ALL products
        $sharedKeys = $productSpecKeys[0];
        for ($i = 1; $i < count($productSpecKeys); $i++) {
            $sharedKeys = array_intersect($sharedKeys, $productSpecKeys[$i]);
        }

        // Get the actual spec objects for shared keys
        $sharedSpecs = [];
        foreach ($products as $productIndex => $product) {
            foreach ($product->specifications as $spec) {
                if (in_array($spec->key, $sharedKeys)) {
                    if (!isset($sharedSpecs[$spec->key])) {
                        $sharedSpecs[$spec->key] = [];
                    }
                    $sharedSpecs[$spec->key][$productIndex] = $spec;
                }
            }
        }

        return $sharedSpecs;
    }

    /**
     * Group specifications by their specification groups
     */
    private function groupSpecifications(array $sharedSpecs): array
    {
        $grouped = [];

        foreach ($sharedSpecs as $key => $productSpecs) {
            $firstSpec = reset($productSpecs);
            $groupId = $firstSpec->specification_group_id;
            $groupName = $firstSpec->specificationGroup?->name ?? 'General';

            if (!isset($grouped[$groupId])) {
                $grouped[$groupId] = [
                    'id' => $groupId,
                    'name' => $groupName,
                    'specifications' => []
                ];
            }

            $grouped[$groupId]['specifications'][$key] = $productSpecs;
        }

        return array_values($grouped);
    }

    /**
     * Identify which specifications have different values across products
     */
    private function identifyDifferences(array $groupedSpecs, array $products): array
    {
        $differences = [];

        foreach ($groupedSpecs as $group) {
            foreach ($group['specifications'] as $key => $productSpecs) {
                $values = array_map(fn($spec) => $spec->value, $productSpecs);
                $uniqueValues = array_unique($values);

                if (count($uniqueValues) > 1) {
                    $differences[] = [
                        'key' => $key,
                        'group' => $group['name'],
                        'values' => $values
                    ];
                }
            }
        }

        return $differences;
    }

    /**
     * Format products for response
     */
    private function formatProducts(array $products): array
    {
        return array_map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'price' => $product->price,
                'sale_price' => $product->sale_price,
                'image' => $product->main_image_url ?? $product->image,
                'brand' => $product->brand?->name,
                'category' => $product->category?->name
            ];
        }, $products);
    }

    /**
     * Generate AI summary of key differences (data-backed only)
     */
    private function generateAISummary(array $products, array $differences): ?string
    {
        if (empty($differences)) {
            return null;
        }

        // Only return summary if we have meaningful differences
        if (count($differences) < 2) {
            return null;
        }

        // Build a simple, factual summary
        $summary = "Key differences between these products:\n\n";

        foreach (array_slice($differences, 0, 5) as $diff) {
            $summary .= "• {$diff['key']}: ";
            $summary .= implode(' vs ', $diff['values']);
            $summary .= "\n";
        }

        return trim($summary);
    }
}