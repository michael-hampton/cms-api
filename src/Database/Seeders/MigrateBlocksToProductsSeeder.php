<?php
// src/Database/Seeders/MigrateBlocksToProductsSeeder.php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Framework\Support\Str;
use App\Models\Block;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\Site;

class MigrateBlocksToProductsSeeder extends Seeder
{
    private array $stats = [
        'total_blocks' => 0,
        'matched' => 0,
        'created' => 0,
        'skipped' => 0,
        'categories_created' => 0,
        'errors' => []
    ];

    // Common product category keywords mapping
    private array $categoryKeywords = [
        'phone' => 'Smartphones',
        'smartphone' => 'Smartphones',
        'iphone' => 'Smartphones',
        'android' => 'Smartphones',
        'mobile' => 'Smartphones',

        'laptop' => 'Laptops',
        'notebook' => 'Laptops',
        'macbook' => 'Laptops',
        'chromebook' => 'Laptops',

        'tv' => 'TVs',
        'television' => 'TVs',
        'oled' => 'TVs',
        'qled' => 'TVs',

        'headphone' => 'Audio',
        'headphones' => 'Audio',
        'earbuds' => 'Audio',
        'speaker' => 'Audio',
        'speakers' => 'Audio',
        'soundbar' => 'Audio',

        'tablet' => 'Tablets',
        'ipad' => 'Tablets',

        'watch' => 'Smartwatches',
        'smartwatch' => 'Smartwatches',

        'camera' => 'Cameras',
        'dslr' => 'Cameras',
        'mirrorless' => 'Cameras',

        'game' => 'Gaming',
        'gaming' => 'Gaming',
        'console' => 'Gaming',
        'playstation' => 'Gaming',
        'xbox' => 'Gaming',
        'nintendo' => 'Gaming',

        'keyboard' => 'Accessories',
        'mouse' => 'Accessories',
        'charger' => 'Accessories',
        'cable' => 'Accessories',
        'case' => 'Accessories',

        'wine' => 'Wine',
        'champagne' => 'Wine',
        'bordeaux' => 'Wine',
        'burgundy' => 'Wine',

        'insurance' => 'Insurance',
        'policy' => 'Insurance',

        'broadband' => 'Utilities',
        'energy' => 'Utilities',

        'credit card' => 'Credit Cards',
        'loan' => 'Loans',
        'mortgage' => 'Mortgages',
    ];

    public function run(): void
    {
        echo "Starting migration of product and deal blocks...\n\n";

        // Get all sites
        $sites = Site::all();

        foreach ($sites as $site) {
            if ($site->id == 1) {
                continue;
            }
            echo "Processing site: {$site->name} (ID: {$site->id})\n";
            $this->processSite($site);
            echo "\n";
        }

        $this->printSummary();
    }

    private function processSite(Site $site): void
    {
        // Get all pages for this site
        $pages = Page::where('site_id', $site->id)->get();

        foreach ($pages as $page) {
            $blocks = Block::where('page_id', $page->id)
                ->whereIn('type', ['product', 'deal'])
                ->get();

            foreach ($blocks as $block) {
                $this->stats['total_blocks']++;

                try {
                    $this->processBlock($block, $site->id, $page);
                } catch (\Exception $e) {
                    $this->stats['errors'][] = [
                        'block_id' => $block->id,
                        'error' => $e->getMessage()
                    ];
                    echo "  ✗ Block {$block->id}: ERROR - {$e->getMessage()}\n";
                }
            }
        }
    }

    private function processBlock(Block $block, int $siteId, Page $page): void
    {
        $data = $block->data;

        // Skip if already has product_id
        if (!empty($data['product_id'])) {
            $this->stats['skipped']++;
            echo "  - Block {$block->id}: Already has product_id\n";
            return;
        }

        // Skip if opted out
        if (!empty($data['opted_out_product_match'])) {
            $this->stats['skipped']++;
            echo "  - Block {$block->id}: Opted out of matching\n";
            return;
        }

        // Extract product details based on block type
        $productDetails = $this->extractProductDetails($data, $block->type);

        if (empty($productDetails['name'])) {
            $this->stats['skipped']++;
            echo "  - Block {$block->id}: No product name\n";
            return;
        }

        // Try to find matching product
        $result = $this->findOrCreateProduct($productDetails, $siteId, $page);

        if (!$result['product_id']) {
            $this->stats['skipped']++;
            echo "  - Block {$block->id}: Failed to create/match product\n";
            return;
        }

        // Update block with product_id
        $data['product_id'] = $result['product_id'];
        $block->data = $data;
        $block->save();

        if ($result['action'] === 'matched') {
            $this->stats['matched']++;
            echo "  ✓ Block {$block->id}: Matched to product {$result['product_id']}\n";
        } else {
            $this->stats['created']++;
            echo "  + Block {$block->id}: Created product {$result['product_id']}\n";
        }
    }

    private function extractProductDetails(array $data, string $type): array
    {
        if ($type === 'deal') {
            return [
                'name' => $data['productName'] ?? $data['title'] ?? null,
                'brand' => $data['brand'] ?? null,
                'price' => $data['price'] ?? 0,
                'sale_price' => $data['salePrice'] ?? 0,
                'description' => $data['description'] ?? null,
                'image' => $data['image']['src'] ?? null
            ];
        }

        // product type
        return [
            'name' => $data['productName'] ?? $data['name'] ?? null,
            'brand' => $data['brand'] ?? null,
            'price' => $data['price'] ?? 0,
            'sale_price' => $data['salePrice'] ?? 0,
            'description' => $data['description'] ?? null,
            'image' => $data['image']['src'] ?? null
        ];
    }

    private function findOrCreateProduct(array $details, int $siteId, Page $page): array
    {
        // Normalize the product name for searching
        $normalizedName = $this->normalizeProductName($details['name']);

        // Search for similar products
        $products = Product::where('site_id', $siteId)
            ->where(function ($query) use ($normalizedName, $details) {
                $query->whereRaw('LOWER(name) LIKE :name1', ['name1' => '%' . $normalizedName . '%'])
                    ->orWhereRaw('LOWER(name) LIKE :name2', ['name2' => $normalizedName . '%'])
                    ->orWhereRaw('LOWER(name) = :name3', ['name3' => $normalizedName]);

            })
            ->limit(10)
            ->get();

        $bestMatch = null;
        $highestSimilarity = 0;

        foreach ($products as $product) {
            // Check for exact match first
            if (strtolower(trim($product->name)) === strtolower(trim($details['name']))) {
                return [
                    'product_id' => $product->id,
                    'action' => 'matched'
                ];
            }

            // Get brand name if exists
            $brandName = null;
            if ($product->brand_id) {
                $brand = Brand::find($product->brand_id);
                $brandName = $brand ? $brand->name : null;
            }

            // Calculate similarity
            $similarity = $this->calculateSimilarity(
                $details['name'],
                $product->name,
                $details['brand'],
                $brandName
            );

            if ($similarity > $highestSimilarity) {
                $highestSimilarity = $similarity;
                $bestMatch = $product;
            }
        }

        // If we have a high confidence match (>85%), use it
        if ($bestMatch && $highestSimilarity > 0.85) {
            echo "    → High similarity match ({$highestSimilarity})\n";
            return [
                'product_id' => $bestMatch->id,
                'action' => 'matched'
            ];
        }

        // Create new product
        $productId = $this->createProduct($details, $siteId, $page);
        return [
            'product_id' => $productId,
            'action' => 'created'
        ];
    }

    private function normalizeProductName(string $name): string
    {
        // Convert to lowercase
        $name = strtolower($name);

        // Remove common words
        $commonWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at'];
        $words = explode(' ', $name);
        $words = array_filter($words, fn($word) => !in_array($word, $commonWords));

        return implode(' ', $words);
    }

    private function calculateSimilarity(
        string  $name1,
        string  $name2,
        ?string $brand1 = null,
        ?string $brand2 = null
    ): float
    {
        // Normalize names
        $norm1 = $this->normalizeProductName($name1);
        $norm2 = $this->normalizeProductName($name2);

        // Calculate Levenshtein distance
        $distance = levenshtein($norm1, $norm2);
        $maxLength = max(strlen($norm1), strlen($norm2));

        if ($maxLength === 0) {
            return 0;
        }

        // Convert to similarity score (0-1)
        $nameSimilarity = 1 - ($distance / $maxLength);

        // Boost score if brands match
        $brandBoost = 0;
        if ($brand1 && $brand2 && strtolower(trim($brand1)) === strtolower(trim($brand2))) {
            $brandBoost = 0.15;
        }

        return min(1.0, $nameSimilarity + $brandBoost);
    }

    private function createProduct(array $details, int $siteId, Page $page): ?int
    {
        $slug = Str::slug($details['name']);

        // Check if slug exists, if so make it unique
        $existingSlug = Product::where('slug', $slug)
            ->where('site_id', $siteId)
            ->first();

        if ($existingSlug) {
            $slug = $slug . '-' . time();
        }

        // Handle brand - find or create if provided
        $brandId = null;
        if (!empty($details['brand'])) {
            $brandId = $this->findOrCreateBrand($details['brand'], $siteId);
        }

        // Find or create suitable category
        $categoryId = $this->findOrCreateCategory($details, $siteId, $page);

        // Create product
        $product = Product::create([
            'name' => $details['name'],
            'brand_id' => $brandId,
            'category_id' => !empty($categoryId) ? $categoryId : null,
            'price' => $details['price'],
            'sale_price' => $details['sale_price'],
            'description' => $details['description'],
            'image' => $details['image'],
            'site_id' => $siteId,
            'is_active' => true,
            'slug' => $slug
        ]);

        echo "    + Created new product\n";

        return $product->id;
    }

    private function findOrCreateBrand(string $brandName, int $siteId): int
    {
        // Try to find existing brand
        $brand = Brand::whereRaw('LOWER(name) = :brand_name', [
            'brand_name' => strtolower(trim($brandName))
        ])
            ->where('site_id', $siteId)
            ->first();

        if ($brand) {
            return $brand->id;
        }

        // Create new brand
        $slug = Str::slug($brandName);

        // Check if slug exists
        $existingSlug = Brand::where('slug', $slug)
            ->where('site_id', $siteId)
            ->first();

        if ($existingSlug) {
            $slug = $slug . '-' . time();
        }

        $brand = Brand::create([
            'name' => $brandName,
            'slug' => $slug,
            'site_id' => $siteId,
            'is_active' => true
        ]);

        return $brand->id;
    }

    private function findOrCreateCategory(array $productDetails, int $siteId, Page $page): ?int
    {
        // Strategy 1: Check if the page has categories - use the first one
        $pageCategories = $page->categories;
        if ($pageCategories && $pageCategories->count() > 0) {
            $category = $pageCategories->first();

            if ($category->site_id == $siteId) {
                echo "      → Using page category: {$category->name}\n";
                return $category->id;
            }
        }

        // Strategy 2: Try to infer category from product name
        $inferredCategory = $this->inferCategoryFromProductName($productDetails['name']);
        if ($inferredCategory) {
            $category = Category::where('name', $inferredCategory)
                ->where('site_id', $siteId)
                ->first();

            if ($category) {
                echo "      → Using inferred category: {$category->name}\n";
                return $category->id;
            }

            // Create the inferred category
            $category = $this->createCategory($inferredCategory, $siteId);
            if ($category) {
                echo "      + Created inferred category: {$category->name}\n";
                $this->stats['categories_created']++;
                return $category->id;
            }
        }

        // Strategy 3: Try to infer from brand
        if (!empty($productDetails['brand'])) {
            $inferredFromBrand = $this->inferCategoryFromBrand($productDetails['brand']);
            if ($inferredFromBrand) {
                $category = Category::where('name', $inferredFromBrand)
                    ->where('site_id', $siteId)
                    ->first();

                if ($category) {
                    echo "      → Using brand-inferred category: {$category->name}\n";
                    return $category->id;
                }

                // Create the brand-inferred category
                $category = $this->createCategory($inferredFromBrand, $siteId);
                if ($category) {
                    echo "      + Created brand-inferred category: {$category->name}\n";
                    $this->stats['categories_created']++;
                    return $category->id;
                }
            }
        }

        // Strategy 4: Check if site has a "Products" or "All Products" category
        $genericCategories = ['Products', 'All Products', 'Shop', 'Store'];
        foreach ($genericCategories as $genericName) {
            $category = Category::where('name', $genericName)
                ->where('site_id', $siteId)
                ->first();

            if ($category) {
                echo "      → Using generic category: {$category->name}\n";
                return $category->id;
            }
        }

        // Strategy 5: Create a default "Products" category
        $category = $this->createCategory('Products', $siteId);
        if ($category) {
            echo "      + Created default category: Products\n";
            $this->stats['categories_created']++;
            return $category->id;
        }

        $category = Category::where('site_id', $siteId)->first();
        return $category->id;


        return null;
    }

    private function inferCategoryFromProductName(string $productName): ?string
    {
        $lowerName = strtolower($productName);

        // Check against keyword mapping
        foreach ($this->categoryKeywords as $keyword => $category) {
            if (strpos($lowerName, $keyword) !== false) {
                return $category;
            }
        }

        return null;
    }

    private function createCategory(string $name, int $siteId): ?Category
    {
        try {
            $slug = Str::slug($name);

            // Check if slug exists
            $existingSlug = Category::where('slug', $slug)
                ->where('site_id', $siteId)
                ->first();

            if ($existingSlug) {
                $slug = $slug . '-' . time();
            }

            return Category::create([
                'name' => $name,
                'slug' => $slug,
                'site_id' => $siteId,
                'is_active' => true
            ]);
        } catch (\Exception $e) {
            echo "      ! Failed to create category: {$e->getMessage()}\n";
            return null;
        }
    }

    private function inferCategoryFromBrand(string $brand): ?string
    {
        $brandMapping = [
            'apple' => 'Smartphones',
            'samsung' => 'Electronics',
            'sony' => 'Electronics',
            'lg' => 'Electronics',
            'microsoft' => 'Computing',
            'dell' => 'Laptops',
            'hp' => 'Laptops',
            'lenovo' => 'Laptops',
            'asus' => 'Computing',
            'bose' => 'Audio',
            'jbl' => 'Audio',
            'sennheiser' => 'Audio',
            'nintendo' => 'Gaming',
            'playstation' => 'Gaming',
            'xbox' => 'Gaming',
        ];

        $lowerBrand = strtolower($brand);

        foreach ($brandMapping as $brandKey => $category) {
            if (strpos($lowerBrand, $brandKey) !== false) {
                return $category;
            }
        }

        return null;
    }

    private function printSummary(): void
    {
        echo "\n";
        echo "==========================================\n";
        echo "MIGRATION SUMMARY\n";
        echo "==========================================\n";
        echo "Total blocks processed: {$this->stats['total_blocks']}\n";
        echo "Matched to existing:    {$this->stats['matched']}\n";
        echo "Created new products:   {$this->stats['created']}\n";
        echo "Categories created:     {$this->stats['categories_created']}\n";
        echo "Skipped:                {$this->stats['skipped']}\n";
        echo "Errors:                 " . count($this->stats['errors']) . "\n";

        if (!empty($this->stats['errors'])) {
            echo "\nERRORS:\n";
            foreach ($this->stats['errors'] as $error) {
                echo "  Block {$error['block_id']}: {$error['error']}\n";
            }
        }

        echo "==========================================\n";
    }

    public function rollback(): void
    {
        echo "Rolling back block-to-product migration...\n";

        $blocks = Block::whereIn('type', ['product', 'deal'])->get();

        $count = 0;
        foreach ($blocks as $block) {
            $data = $block->data;

            if (isset($data['product_id'])) {
                unset($data['product_id']);
                $block->data = $data;
                $block->save();
                $count++;
            }
        }

        echo "✓ Rollback complete - removed product_id from {$count} blocks\n";
        echo "Note: Products and categories created by migration were not deleted.\n";
        echo "Run a separate cleanup script if you need to remove those.\n";
    }
}