<?php

namespace App\Services;

use App\Framework\Support\SiteContext;
use App\Models\Product;
use App\Models\FeaturedDeal;
use App\Models\ProductVariant;
use App\Models\ProductMerchant;
use App\Models\Category;
use App\Models\Brand;

class DealsService
{
    public function getTodaysDeals(int $limit = 20): array
    {
        $siteId = SiteContext::getId();
        $today = date('Y-m-d');

        // Get featured deals first
        $featuredDeals = FeaturedDeal::where('site_id', $siteId)
            ->where('featured_date', $today)
            ->where('is_active', true)
            ->orderBy('position')
            ->limit($limit)
            ->get();

        if ($featuredDeals->isEmpty()) {
            // Auto-generate deals based on discount rules
            return $this->generateDefaultDeals($limit);
        }

        return $this->enrichDealsData($featuredDeals);
    }

    private function generateDefaultDeals(int $limit, array $filters = []): array
    {
        $siteId = SiteContext::getId();

        // Build base query
        $query = Product::where('site_id', $siteId)
            ->where('is_active', true)
            ->whereNotNull('sale_price');
            //->where('sale_price', '<', 'price');

        // Apply default price range unless overridden
        if (!isset($filters['minPrice']) && !isset($filters['maxPrice'])) {
            $query->whereBetween('sale_price', [10, 300]);
        }

        $query->with(['variants.merchants', 'merchants', 'images', 'brand', 'category', 'approvedReviews']);

        $products = $query->get();

        $deals = [];
        foreach ($products as $product) {
            $bestDeal = $this->getBestDealForProduct($product);
            if ($bestDeal) {
                $deals[] = $bestDeal;
            }
        }

        // Sort by discount percentage
        usort($deals, fn($a, $b) => $b['discount_percentage'] <=> $a['discount_percentage']);

        return array_slice($deals, 0, $limit);
    }

    private function getBestDealForProduct($product): ?array
    {
        $bestPrice = $product->sale_price ?? $product->price;
        $originalPrice = $product->price;
        $source = 'product';
        $variantId = null;
        $merchantId = null;
        $variantName = null;
        $merchantName = null;

        // Check variants
        if ($product->variants) {
            foreach ($product->variants as $variant) {
                if (!$variant->is_active) continue;

                if ($variant->sale_price && $variant->sale_price < $bestPrice) {
                    $bestPrice = $variant->sale_price;
                    $originalPrice = $variant->price;
                    $source = 'variant';
                    $variantId = $variant->id;
                    $variantName = $variant->name;
                }

                // Check merchant prices for this variant
                if ($variant->merchants) {
                    foreach ($variant->merchants as $merchant) {
                        if (!$merchant->is_available) continue;

                        if ($merchant->effective_sale_price && $merchant->effective_sale_price < $bestPrice) {
                            $bestPrice = $merchant->effective_sale_price;
                            $originalPrice = $merchant->effective_price;
                            $source = 'merchant';
                            $variantId = $variant->id;
                            $merchantId = $merchant->merchant_id;
                            $variantName = $variant->name;
                            $merchantName = $merchant->merchant?->name ?? $merchant->name;
                        }
                    }
                }
            }
        }

        // Check direct merchant prices (no variant)
        if ($product->merchants) {
            foreach ($product->merchants as $merchant) {
                if (!$merchant->is_available) continue;

                if ($merchant->effective_sale_price && $merchant->effective_sale_price < $bestPrice) {
                    $bestPrice = $merchant->effective_sale_price;
                    $originalPrice = $merchant->effective_price;
                    $source = 'merchant';
                    $merchantId = $merchant->merchant_id;
                    $merchantName = $merchant->merchant?->name ?? $merchant->name;
                }
            }
        }

        if ($originalPrice <= 0 || $bestPrice >= $originalPrice) {
            return null;
        }

        $discount = round((($originalPrice - $bestPrice) / $originalPrice) * 100);

        return [
            'product_id' => $product->id,
            'variant_id' => $variantId,
            'merchant_id' => $merchantId,
            'title' => $product->name,
            'slug' => $product->slug,
            'image' => $product->main_image_url,
            'original_price' => $originalPrice,
            'sale_price' => $bestPrice,
            'discount_percentage' => $discount,
            'rating' => $product->average_rating ?? 0,
            'review_count' => $product->review_count ?? 0,
            'source' => $source,
            'variant_name' => $variantName,
            'merchant_name' => $merchantName,
            'category_id' => $product->category_id,
            'category_name' => $product->category?->name,
            'brand_id' => $product->brand_id,
            'brand_name' => $product->brand?->name,
        ];
    }

    private function enrichDealsData($featuredDeals): array
    {
        $deals = [];
        foreach ($featuredDeals as $deal) {
            $product = Product::with(['variants.merchants', 'merchants', 'images', 'brand', 'category', 'approvedReviews'])
                ->find($deal->product_id);

            if (!$product) continue;

            $dealData = $this->getBestDealForProduct($product);
            if ($dealData) {
                $deals[] = $dealData;
            }
        }
        return $deals;
    }

    public function refreshTodaysDeals(): array
    {
        $siteId = SiteContext::getId();
        $today = date('Y-m-d');

        // Deactivate old featured deals
        FeaturedDeal::where('site_id', $siteId)
            ->where('featured_date', '<', $today)
            ->update(['is_active' => false]);

        // Deactivate current day deals
        FeaturedDeal::where('site_id', $siteId)
            ->where('featured_date', $today)
            ->update(['is_active' => false]);

        // Generate new deals
        $deals = $this->generateDefaultDeals(20);

        // Save as featured deals
        foreach ($deals as $index => $deal) {
            FeaturedDeal::create([
                'product_id' => $deal['product_id'],
                'variant_id' => $deal['variant_id'],
                'merchant_id' => $deal['merchant_id'],
                'site_id' => $siteId,
                'featured_date' => $today,
                'position' => $index,
                'is_active' => true
            ]);
        }

        return $deals;
    }

    public function getFilteredDeals(array $filters): array
    {
        $siteId = SiteContext::getId();

        // Start with base query
        $query = Product::where('site_id', $siteId)
            ->where('is_active', true)
            ->with(['variants.merchants', 'merchants', 'images', 'brand', 'category', 'approvedReviews']);

        // Apply tab-specific filters
        if (isset($filters['tab'])) {
            switch ($filters['tab']) {
                case 'under25':
                    $filters['maxPrice'] = 25;
                    break;
                case 'over50':
                    $filters['discount'] = 50;
                    break;
                case 'vouchers':
                    $filters['hasVoucher'] = true;
                    break;
                default:
                    if (strpos($filters['tab'], 'cat-') === 0) {
                        $categoryId = (int) str_replace('cat-', '', $filters['tab']);
                        $filters['category'] = [$categoryId];
                    }
            }
        }

        // Category filter
        if (!empty($filters['category'])) {
            $query->whereIn('category_id', $filters['category']);
        }

        // Brand filter
        if (!empty($filters['brand'])) {
            $query->whereIn('brand_id', $filters['brand']);
        }

        // Price range filter
        if (isset($filters['minPrice']) || isset($filters['maxPrice'])) {
            if (isset($filters['minPrice'])) {
                $query->where('sale_price', '>=', $filters['minPrice']);
            }
            if (isset($filters['maxPrice'])) {
                $query->where('sale_price', '<=', $filters['maxPrice']);
            }
        }

        // Voucher filter
        if (!empty($filters['hasVoucher'])) {
            $query->whereHas('activeVouchers');
        }

        // Get all products that match filters
        $products = $query->get();

        // Build deals array
        $deals = [];
        foreach ($products as $product) {
            $bestDeal = $this->getBestDealForProduct($product);
            if ($bestDeal) {
                // Apply rating filter
                if (!empty($filters['rating'])) {
                    $minRating = min($filters['rating']);
                    if ($bestDeal['rating'] < $minRating) {
                        continue;
                    }
                }

                // Apply discount filter
                if (isset($filters['discount'])) {
                    if ($bestDeal['discount_percentage'] < $filters['discount']) {
                        continue;
                    }
                }

                $deals[] = $bestDeal;
            }
        }

        // Sort deals
        $sortBy = $filters['sort'] ?? 'discount:desc';
        [$sortField, $sortDir] = explode(':', $sortBy);

        usort($deals, function($a, $b) use ($sortField, $sortDir) {
            $aVal = $a[$sortField === 'price' ? 'sale_price' : $sortField] ?? 0;
            $bVal = $b[$sortField === 'price' ? 'sale_price' : $sortField] ?? 0;

            $result = $aVal <=> $bVal;
            return $sortDir === 'desc' ? -$result : $result;
        });

        // Pagination
        $page = $filters['page'] ?? 1;
        $perPage = $filters['perPage'] ?? 24;
        $total = count($deals);
        $totalPages = (int) ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;

        $paginatedDeals = array_slice($deals, $offset, $perPage);

        return [
            'deals' => $paginatedDeals,
            'total' => $total,
            'pagination' => [
                'currentPage' => (int) $page,
                'totalPages' => $totalPages,
                'perPage' => (int) $perPage,
                'hasNext' => $page < $totalPages,
                'hasPrev' => $page > 1
            ]
        ];
    }
}