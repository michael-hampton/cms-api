<?php

namespace App\Services\PublicContent;

use App\Models\Author;
use App\Models\Page;
use App\Services\Offers\DealsService;
use App\Services\Recommendations\ContentRecommendationService;
use Throwable;

final class PublicContentSupplementaryService
{
    public function __construct(
        private readonly DealsService $deals,
        private readonly ContentRecommendationService $recommendations,
    ) {
    }

    public function for(Page $page, int $siteId, string $siteSlug): array
    {
        return [
            'activity_feed' => $this->activityFeed($siteId, $siteSlug),
            'trending' => $this->trending($siteId, $siteSlug),
            'products' => $this->products($page),
            'deals' => $this->deals(),
            'guest_contributors' => $this->contributors($siteId),
            'newsletter' => ['enabled' => true],
        ];
    }

    private function activityFeed(int $siteId, string $siteSlug): array
    {
        return Page::with(['categories', 'authors', 'comments', 'metadata'])
            ->where('site_id', $siteId)
            ->where('status', 'published')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(static fn($page) => [
                'id' => (int)$page->id,
                'title' => (string)$page->title,
                'slug' => (string)$page->slug,
                'url' => '/' . $siteSlug . '/' . $page->slug,
                'image' => $page->metadata->featured_image ?? null,
                'category' => $page->categories?->first()?->name,
                'authors' => $page->authors?->take(2)->pluck('name')->toArray() ?? [],
                'comment_count' => $page->comments?->count() ?? 0,
                'created_at' => $page->created_at,
            ])->toArray();
    }

    private function trending(int $siteId, string $siteSlug): array
    {
        try {
            $pages = $this->recommendations->getTrendingConversations($siteId, 3);
        } catch (Throwable) {
            return [];
        }

        return $pages->map(static fn($page) => [
            'id' => (int)$page->id,
            'title' => (string)$page->title,
            'slug' => (string)$page->slug,
            'url' => '/' . $siteSlug . '/' . $page->slug,
            'image' => $page->metadata->featured_image ?? null,
            'category' => $page->categories?->first()?->name,
            'like_count' => (int)($page->like_count_24h ?? 0),
            'comment_count' => (int)($page->comment_count_24h ?? 0),
        ])->toArray();
    }

    private function products(Page $page): array
    {
        if (!$page->products) {
            return [];
        }

        return $page->products->map(static fn($product) => [
            'id' => (int)$product->id,
            'name' => (string)($product->name ?? ''),
            'slug' => (string)($product->slug ?? ''),
            'image' => $product->image ?? null,
            'price' => $product->price ?? null,
            'sale_price' => $product->sale_price ?? null,
        ])->toArray();
    }

    private function deals(): array
    {
        try {
            $deals = $this->deals->getTodaysDeals(10);
            return method_exists($deals, 'toArray') ? $deals->toArray() : (array)$deals;
        } catch (Throwable) {
            return [];
        }
    }

    private function contributors(int $siteId): array
    {
        return Author::where('site_id', $siteId)
            ->where('is_active', true)
            ->where('is_guest', true)
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get()
            ->map(static fn($author) => [
                'id' => (int)$author->id,
                'name' => (string)($author->name ?? ''),
                'slug' => (string)($author->slug ?? ''),
                'bio' => $author->bio ?? null,
                'image' => $author->avatar ?? null,
            ])->toArray();
    }
}
