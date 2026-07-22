<?php

namespace App\Services\Offers;

use App\Enums\Boost\BoostContext;
use App\Framework\Support\Logger;
use App\Framework\Support\SiteContext;
use App\Repositories\Adverts\Boost\BoostRepository;
use App\Repositories\Offers\DealsRepository;
use App\Repositories\ReviewRepository;
use App\Services\Adverts\Boost\BoostEventService;
use App\Services\Adverts\Boost\BoostRankingService;

class DealsService
{
    private DealsRepository $repository;

    public function __construct(
        private readonly ReviewRepository    $reviewRepository,
        private readonly BoostRankingService $boostRankingService,
        private readonly BoostRepository   $boostRepository,
        private readonly BoostEventService $boostEventService,
        ?DealsRepository                     $repository = null
    )
    {
        $this->repository = $repository ?? new DealsRepository();
    }

    public function getTodaysDeals(int $limit = 20, ?int $siteId = null): array
    {
        $siteId = $siteId ?? SiteContext::getId();
        $today = date('Y-m-d');

        $featuredDeals = $this->repository->getFeaturedDealsByDate($siteId, $today, $limit);

        if (empty($featuredDeals)) {
            return $this->generateDefaultDeals($limit, [], $siteId);
        }

        return $this->enrichDealsData($featuredDeals);
    }

    /**
     * Featured deals only — no invent-defaults fallback.
     * Public content islands must use this so degraded/empty stay honest.
     */
    public function getFeaturedDealsOnly(int $limit = 20, ?int $siteId = null): array
    {
        $siteId = $siteId ?? SiteContext::getId();
        $today = date('Y-m-d');
        $featuredDeals = $this->repository->getFeaturedDealsByDate($siteId, $today, $limit);

        if (empty($featuredDeals)) {
            return [];
        }

        return $this->enrichDealsData($featuredDeals);
    }

    private function generateDefaultDeals(int $limit, array $filters = [], ?int $siteId = null): array
    {
        $siteId = $siteId ?? SiteContext::getId();

        $minPrice = $filters['minPrice'] ?? null;
        $maxPrice = $filters['maxPrice'] ?? null;

        $products = $this->repository->getProductsForDeals($siteId, $minPrice, $maxPrice);

        $deals = [];
        foreach ($products as $productData) {
            $product = $this->repository->findProductById($productData['id']);

            if ($product) {
                $bestDeal = $this->getBestDealForProduct($product);

                if ($bestDeal) {
                    $deals[] = $bestDeal;
                }
            }
        }

        usort($deals, fn($a, $b) => $b['discount_percentage'] <=> $a['discount_percentage']);

        return array_slice($deals, 0, $limit);
    }

    private function getBestDealForProduct($product): ?array
    {
        $bestPrice = $product->sale_price > 0 ? $product->sale_price : $product->price;

        $originalPrice = $product->price;
        $source = 'product';
        $variantId = null;
        $merchantId = null;
        $variantName = null;
        $merchantName = null;

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
            $product = $this->repository->findProductById($deal['product_id']);

            if (!$product) continue;

            $dealData = $this->getBestDealForProduct($product);
            if ($dealData) {
                $deals[] = $dealData;
            }
        }
        return $deals;
    }

    public function refreshTodaysDeals(?int $siteId = null): array
    {
        $siteId = $siteId ?? SiteContext::getId();
        $today = date('Y-m-d');

        $this->repository->deactivateOldFeaturedDeals($siteId, $today);
        $this->repository->deactivateFeaturedDealsByDate($siteId, $today);

        $deals = $this->generateDefaultDeals(20, [], $siteId);

        foreach ($deals as $index => $deal) {
            $this->repository->createFeaturedDeal([
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

    public function getFilteredDeals(array $filters, ?int $siteId = null): array
    {
        $siteId = $siteId ?? SiteContext::getId();

        // Fetch boosted IDs first and pass to repository so DB handles ordering
        $boostedIds = [];
        try {
            $boostedIds = $this->boostRankingService->getActiveBoostedIds(BoostContext::Deals->value);

        } catch (\Exception $e) {
            Logger::error('Failed to fetch boosted IDs for deals', ['error' => $e->getMessage()]);
        }

        $products = $this->repository->getFilteredProducts($siteId, $filters, $boostedIds);

        // ── Boost ranking ──────────────────────────────────────────────
        try {
            $products['data'] = $this->boostRankingService->applyRanking(
                $products['data'],
                BoostContext::Deals->value
            );
        } catch (\Exception $e) {
            Logger::error('Boost ranking failed in deals filter', ['error' => $e->getMessage()]);
        }

        $productIds = array_unique(array_column($products['data']->toArray(), 'id'));

        // ── Record impressions for boosted products ────────────────────
        $this->recordBoostImpressions($productIds, BoostContext::Deals->value);

        $topReviews = $this->reviewRepository->getTopReview($productIds)->keyBy('product_id');

        $formattedProducts = $products['data']->map(function ($product) use ($topReviews, $boostedIds) {
            $data = $this->formatProductForDeals($product);
            $data['top_review'] = $topReviews->get($data['product_id'])?->toArray() ?? [];
            $data['is_boosted'] = in_array($product->id, $boostedIds);
            return $data;
        })->toArray();

        return [
            'data' => $formattedProducts,
            'total' => $products['pagination']['total'],
            'pagination' => $products['pagination']
        ];
    }

    private function recordBoostImpressions(array $productIds, string $context): void
    {
        if (empty($productIds)) {
            return;
        }

        $sessionHash = md5(session_id() ?: uniqid('deals_', true));
        $now = now();

        // Create a ProductImpression record for every visible product
        try {
            \App\Models\ProductImpression::insert(
                array_map(fn($id) => [
                    'product_id' => $id,
                    'context' => $context,
                    'viewed_at' => $now,
                ], $productIds)
            );
        } catch (\Exception $e) {
            Logger::error('Failed to record product impressions in deals', ['error' => $e->getMessage()]);
        }

        // Additionally record a boost event for products that have an active boost
        foreach ($productIds as $productId) {
            try {
                $boost = $this->boostRepository->findActiveForTarget('product', $productId);

                if ($boost) {
                    $this->boostEventService->recordImpression($boost->id, $sessionHash, ['context' => 'deals']);
                }
            } catch (\Exception $e) {
                Logger::error('Failed to record boost impression in deals', [
                    'product_id' => $productId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function formatProductForDeals($product): array
    {
        $discountPercentage = 0;
        $finalPrice = $product->price;

        if ($product->sale_price && $product->sale_price > 0 && $product->sale_price < $product->price) {
            $finalPrice = $product->sale_price;
            $discountPercentage = round((($product->price - $product->sale_price) / $product->price) * 100);
        }

        $mainImage = null;
        if ($product->main_image_url) {
            $mainImage = $product->main_image_url;
        } elseif ($product->images && count($product->images) > 0) {
            $mainImage = $product->images[0]->url ?? $product->images[0]->path ?? null;
        } elseif ($product->image) {
            $mainImage = $product->image;
        }

        $averageRating = 0;
        $reviewCount = 0;
        if ($product->approvedReviews && count($product->approvedReviews) > 0) {
            $reviews = $product->approvedReviews->toArray();
            $reviewCount = count($reviews);
            $averageRating = count($reviews)
                ? array_sum(array_column($reviews, 'rating')) / count($reviews)
                : 0;
        }

        $lowestMerchantPrice = null;
        if ($product->availableMerchants && $product->availableMerchants->count() > 0) {
            $merchantPrices = $product->availableMerchants->map(function ($merchant) {
                return $merchant->sale_price && $merchant->sale_price > 0 ? $merchant->sale_price : $merchant->price;
            })->toArray();

            $lowestMerchantPrice = min($merchantPrices);
        }

        $hasVariants = false;
        $lowestVariantPrice = null;
        if ($product->variants && $product->variants->count() > 0) {
            $hasVariants = true;
            $variantPrices = [];
            foreach ($product->variants as $variant) {
                if ($variant->in_stock) {
                    $variantPrice = $variant->sale_price > 0 ? $variant->sale_price : $variant->price;
                    $variantPrices[] = $variantPrice;
                }
            }
            if (count($variantPrices) > 0) {
                $lowestVariantPrice = min($variantPrices);
            }
        }

        return [
            'product_id' => $product->id,
            'title' => $product->title ?? $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'image' => $mainImage,
            'original_price' => (float)$product->price,
            'sale_price' => $product->sale_price > 0 ? (float)$product->sale_price : null,
            'final_price' => (float)$finalPrice,
            'discount_percentage' => $discountPercentage,
            'brand' => $product->brand?->name ?? null,
            'brand_id' => $product->brand_id,
            'category' => $product->category?->name ?? null,
            'category_id' => $product->category_id,
            'stock_quantity' => $product->stock_quantity ?? 0,
            'in_stock' => ($product->stock_quantity ?? 0) > 0,
            'average_rating' => $averageRating,
            'review_count' => $reviewCount,
            'has_variants' => $hasVariants,
            'lowest_variant_price' => $lowestVariantPrice,
            'lowest_merchant_price' => $lowestMerchantPrice,
            'created_at' => $product->created_at,
            'updated_at' => $product->updated_at,
            'merchant_count' => count($product->merchants ?? []),
            'availableMerchants' => $product->availableMerchants?->toArray() ?? [],
        ];
    }
}