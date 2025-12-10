<?php

namespace App\Services;

use App\Models\Brand;
use App\Repositories\ProductRepository;

class ProductMatchingService
{
    private ProductRepository $productRepository;

    public function __construct(ProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    /**
     * Find potential product matches based on name
     */
    public function findMatches(string $productName, ?string $brand = null, ?int $siteId = null): array
    {
        if (empty(trim($productName))) {
            return [];
        }

        // Normalize the search term
        $normalizedName = $this->normalizeProductName($productName);

        // Search for products with similar names
        $products = $this->productRepository->searchByName($normalizedName, $siteId);

        $matches = [];

        foreach ($products as $product) {

            if ($product->name == $productName) {
                $matches[] = [
                    'product' => $product,
                    'similarity' => 8,
                    'confidence' => $this->getConfidenceLevel(8)
                ];

                continue;
            }

            $similarity = $this->calculateSimilarity($productName, $product->name, $brand, $product->brand);

            // Only include matches with > 70% similarity
            if ($similarity > 0.7) {
                $matches[] = [
                    'product' => $product,
                    'similarity' => $similarity,
                    'confidence' => $this->getConfidenceLevel($similarity)
                ];
            }
        }

        // Sort by similarity (highest first)
        usort($matches, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        return $matches;
    }

    /**
     * Normalize product name for comparison
     */
    private function normalizeProductName(string $name): string
    {
        // Convert to lowercase
        $name = strtolower($name);

        // Remove common words
        $commonWords = ['the', 'an', 'and', 'or', 'but', 'in', 'on', 'at'];
        $words = explode(' ', $name);
        $words = array_filter($words, fn($word) => !in_array($word, $commonWords));

        return implode(' ', $words);
    }

    /**
     * Get confidence level description
     */
    private function getConfidenceLevel(float $similarity): string
    {
        if ($similarity >= 0.95) return 'exact';
        if ($similarity >= 0.85) return 'high';
        if ($similarity >= 0.75) return 'medium';
        return 'low';
    }

    /**
     * Calculate similarity between two product names
     */
    private function calculateSimilarity(
        string  $name1,
        string  $name2,
        ?string $brand1 = null,
        ?Brand  $brand2 = null
    ): float
    {
        // Normalize names
        $norm1 = $this->normalizeProductName($name1);
        $norm2 = $this->normalizeProductName($name2);

        // Calculate Levenshtein distance
        $distance = levenshtein($norm1, $norm2);
        $maxLength = max(strlen($norm1), strlen($norm2));

        // Convert to similarity score (0-1)
        $nameSimilarity = 1 - ($distance / $maxLength);

        // Boost score if brands match
        $brandBoost = 0;
        if ($brand1 && $brand2 && strtolower(trim($brand1)) === strtolower(trim($brand2->name))) {
            $brandBoost = 0.15;
        }

        return min(1.0, $nameSimilarity + $brandBoost);
    }
}