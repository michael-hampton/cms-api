<?php

declare(strict_types=1);

namespace App\Services\Newsletter;

use App\Framework\Support\Logger;
use App\Models\Member;
use App\Services\Recommendations\ContentRecommendationService;
use App\Services\Recommendations\ProductRecommendationService;

/**
 * Resolves recommendation data at newsletter render time.
 *
 * Called by the two recommendation block renderers.
 * Never called from Angular — the frontend only stores block configuration
 * (placement, limit, fallback) and mock preview data.
 *
 * Fallback chain (articles):
 *   personalised → trending → recent
 *
 * Fallback chain (products):
 *   personalised → top_sellers → popular
 *
 * All failures are caught and logged; an empty array is returned so the
 * renderer can silently skip the block rather than break the send.
 */
class RecommendationResolver
{
    /** Per-request in-memory cache keyed by "{type}:{memberId}:{siteId}:{limit}" */
    private array $cache = [];

    public function __construct(
        private readonly ContentRecommendationService $contentRecommendations,
        private readonly ProductRecommendationService $productRecommendations,
        private readonly Logger                       $logger,
    )
    {
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * @return array<int, array{title: string, slug: string, description: string, hero_image_url: string|null}>
     */
    public function resolveArticles(
        int     $siteId,
        int     $limit,
        string  $fallback,
        ?Member $member = null,
    ): array
    {
        $cacheKey = "articles:{$siteId}:{$member?->id}:{$limit}:{$fallback}";

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        $result = $this->fetchArticles($siteId, $limit, $fallback, $member);

        $this->cache[$cacheKey] = $result;

        return $result;
    }

    /**
     * @return array<int, array{name: string, price: float, currency: string, link: string|null, image_url: string|null}>
     */
    public function resolveProducts(
        int     $siteId,
        int     $limit,
        string  $fallback,
        ?Member $member,
    ): array
    {
        $cacheKey = "products:{$siteId}:{$member?->id}:{$limit}:{$fallback}";

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        $result = $this->fetchProducts($siteId, $limit, $fallback, $member);

        $this->cache[$cacheKey] = $result;

        return $result;
    }

    public function clearCache(): void
    {
        $this->cache = [];
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function fetchArticles(int $siteId, int $limit, string $fallback, ?Member $member): array
    {
        try {
            // Phase 1: personalised (requires authenticated member)
            if ($member !== null) {
                $pages = $this->contentRecommendations
                    ->getRecommendedForMember($member, $siteId, $limit);

                if ($pages->isNotEmpty()) {
                    return $this->mapPages($pages->take($limit)->all());
                }
            }

            // Phase 2: fallback strategy
            return match ($fallback) {
                'trending' => $this->mapPages(
                    $this->contentRecommendations->getTrendingContent($siteId, $limit)->all()
                ),
                'recent' => $this->mapPages(
                    $this->contentRecommendations
                        ->getLatestContent($siteId, $limit, $member)
                        ->all()
                ),
                default => [],
            };
        } catch (\Throwable $e) {
            $this->logger->error('RecommendationResolver: article fetch failed', [
                'site_id' => $siteId,
                'fallback' => $fallback,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function fetchProducts(int $siteId, int $limit, string $fallback, ?Member $member): array
    {
        try {
            // Phase 1: personalised
            if ($member !== null) {
                $products = $this->productRecommendations
                    ->getRecommendedProducts($member, $siteId, $limit);

                if ($products->isNotEmpty()) {
                    return $this->mapProducts($products->take($limit)->all());
                }
            }

            // Phase 2: fallback strategy
            return match ($fallback) {
                'top_sellers', 'popular' => $this->mapProducts(
                    $this->productRecommendations->getPopularProducts($siteId, $limit)->all()
                ),
                default => [],
            };
        } catch (\Throwable $e) {
            $this->logger->error('RecommendationResolver: product fetch failed', [
                'site_id' => $siteId,
                'fallback' => $fallback,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function mapPages(array $pages): array
    {
        return array_values(array_map(static function (object|array $page): array {
            $p = is_array($page) ? (object)$page : $page;

            return [
                'title' => $p->title ?? '',
                'slug' => $p->slug ?? '',
                'description' => $p->meta_description ?? $p->listing_synopsis ?? '',
                'hero_image_url' => $p->listing_image_url ?? $p->hero_image_url ?? null,
            ];
        }, $pages));
    }

    private function mapProducts(array $products): array
    {
        return array_values(array_map(static function (object|array $product): array {
            $p = is_array($product) ? (object)$product : $product;

            return [
                'name' => $p->name ?? '',
                'price' => (float)($p->sale_price ?? $p->price ?? 0),
                'currency' => $p->currency ?? '£',
                'link' => $p->slug ? url("/products/{$p->slug}") : null,
                'image_url' => $p->main_image_url ?? null,
            ];
        }, $products));
    }
}