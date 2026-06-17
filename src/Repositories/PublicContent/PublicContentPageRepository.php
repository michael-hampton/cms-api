<?php

namespace App\Repositories\PublicContent;

use App\Framework\Support\Cache\Cache;
use App\Framework\Support\Collection;
use App\Models\Page;
use App\Models\Site;
use App\Repositories\Repository;

final class PublicContentPageRepository extends Repository
{
    private const CACHE_TTL = 300;

    private const COMPLETE_RELATIONS = [
        'blocks',
        'categories',
        'tags',
        'metadata',
        'seo',
        'settings',
        'social',
        'customFields',
        'customFields.customFieldDefinition',
        'authors',
        'pageAuthors',
        'pageAuthors.author',
        'regionSets',
        'territories',
        'products',
        'owner',
    ];

    public function findPublishedById(int $pageId, int $siteId, array $relations = []): ?Page
    {
        $key = $this->cacheKey('published-id', $siteId, (string) $pageId, $relations);

        return Cache::remember($key, self::CACHE_TTL, function () use ($pageId, $siteId, $relations): ?Page {
            $query = $relations === [] ? Page::query() : Page::with($relations);
            $page = $query
                ->where('id', $pageId)
                ->where('site_id', $siteId)
                ->where('status', 'published')
                ->first();

            return $page instanceof Page ? $page : null;
        });
    }

    public function findPublishedBySlug(int $siteId, string $slug, array $relations = []): ?Page
    {
        $key = $this->cacheKey('published-slug', $siteId, $slug, $relations);

        return Cache::remember($key, self::CACHE_TTL, function () use ($siteId, $slug, $relations): ?Page {
            $query = $relations === [] ? Page::query() : Page::with($relations);
            $page = $query
                ->where('site_id', $siteId)
                ->where('slug', $slug)
                ->where('status', 'published')
                ->first();

            return $page instanceof Page ? $page : null;
        });
    }

    public function findPreviewById(int $pageId, int $siteId, array $relations = []): ?Page
    {
        $key = $this->cacheKey('preview-id', $siteId, (string) $pageId, $relations);

        return Cache::remember($key, self::CACHE_TTL, function () use ($pageId, $siteId, $relations): ?Page {
            $query = $relations === [] ? Page::query() : Page::with($relations);
            $page = $query
                ->where('id', $pageId)
                ->where('site_id', $siteId)
                ->first();

            return $page instanceof Page ? $page : null;
        });
    }

    public function findCompletePreviewById(int $pageId, int $siteId): ?Page
    {
        return $this->findPreviewById($pageId, $siteId, self::COMPLETE_RELATIONS);
    }

    public function findPublishedBySlugForTerritory(int $siteId, string $slug, int $territoryId, array $relations = []): ?Page
    {
        $query = $relations === [] ? Page::query() : Page::with($relations);
        $page = $query
            ->where('site_id', $siteId)
            ->where('slug', $slug)
            ->where('status', 'published')
            ->whereHas('territories', function ($territories) use ($territoryId): void {
                $territories->where('territories.id', $territoryId);
            })
            ->first();

        return $page instanceof Page ? $page : null;
    }

    public function findCompletePublishedBySlug(int $siteId, string $slug): ?Page
    {
        return $this->findPublishedBySlug($siteId, $slug, self::COMPLETE_RELATIONS);
    }

    public function findCompletePublishedBySlugForTerritory(int $siteId, string $slug, int $territoryId): ?Page
    {
        return $this->findPublishedBySlugForTerritory($siteId, $slug, $territoryId, self::COMPLETE_RELATIONS);
    }

    public function getRelatedForTerritory(int $siteId, int $territoryId, int $excludePageId, int $limit = 6): Collection
    {
        return Page::where('site_id', $siteId)
            ->where('status', 'published')
            ->where('id', '!=', $excludePageId)
            ->whereHas('territories', function ($territories) use ($territoryId): void {
                $territories->where('territories.id', $territoryId);
            })
            ->with(['customFields'])
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function findHomepage(Site $site): ?Page
    {
        $pageId = $site->getSetting('homepage_page_id');
        if ($pageId && ($page = $this->findPublishedById((int) $pageId, (int) $site->id))) {
            return $page;
        }

        $configuredSlug = $site->getSetting('homepage_slug', $site->getSetting('homepage_page_slug'));
        if ($configuredSlug && ($page = $this->findPublishedBySlug((int) $site->id, (string) $configuredSlug))) {
            return $page;
        }

        if ($home = $this->findPublishedBySlug((int) $site->id, 'home')) {
            return $home;
        }

        $page = Page::where('site_id', (int) $site->id)
            ->where('status', 'published')
            ->where('page_type', 'landing-page')
            ->orderBy('created_at')
            ->first();

        return $page instanceof Page ? $page : null;
    }

    public static function forgetPage(int $pageId, int $siteId, ?string $slug = null): void
    {
        foreach (['published-id', 'preview-id'] as $scope) {
            Cache::forget(self::baseCacheKey($scope, $siteId, (string) $pageId, []));
            Cache::forget(self::baseCacheKey($scope, $siteId, (string) $pageId, self::COMPLETE_RELATIONS));
        }

        if ($slug !== null && $slug !== '') {
            foreach (['published-slug'] as $scope) {
                Cache::forget(self::baseCacheKey($scope, $siteId, $slug, []));
                Cache::forget(self::baseCacheKey($scope, $siteId, $slug, self::COMPLETE_RELATIONS));
            }
        }
    }

    private function cacheKey(string $scope, int $siteId, string $identity, array $relations): string
    {
        return self::baseCacheKey($scope, $siteId, $identity, $relations);
    }

    private static function baseCacheKey(string $scope, int $siteId, string $identity, array $relations): string
    {
        return sprintf('public-content:%s:%d:%s:%s', $scope, $siteId, sha1($identity), sha1(implode('|', $relations)));
    }

    protected function getModelClass(): string
    {
        return Page::class;
    }
}
