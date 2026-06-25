<?php

namespace App\Services\Url;

use App\Framework\Support\Cache\Cache;
use App\Framework\Support\SiteContext;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Services\PublicContent\Slugs\PublicContentPathResolver;

class DynamicUrlResolver implements UrlResolverInterface
{
    public function __construct(
        private readonly Cache $cache,
        private array $config,
        private readonly PublicContentPathResolver $contentPaths,
    )
    {
        $this->config = array_merge([
            'cache_duration' => 3600,
            'base_url' => config('app.url'),
            'force_trailing_slash' => false,
            'case_sensitive' => false
        ], $config);
    }

    public function resolve(string $path): ?UrlResolutionResult
    {
        $path = $this->normalizePath(ltrim($path, '/'));

        $parts = explode('/', rtrim(trim($path), '/'));

        if (count($parts) === 1) {
            // check if path is a site and if so redirect to homepage
            $site = SiteContext::get();

            if ($site && $site->url_handle) {
                $page = Page::where('slug', $site->url_handle)
                    ->where('site_id', SiteContext::getId())
                    ->first();

                if ($page instanceof Page) {
                    $path = SiteContext::slug() . '/' . $page->slug;
                    return $this->createPageResult($page, $path);
                }
            }
        }

        $page = $this->findPageBySlug($path);

        if (!$page) {
            return $this->checkRelations($path);
        }

        return match ($page->status) {
            'redirect' => $this->createRedirectResult($page, $path),
            'published' => $this->createPageResult($page, $path),
            'draft', 'private' => $this->handleUnpublishedPage($page, $path),
            default => null
        };
    }

    private function findPageBySlug(string $slug): ?Page
    {
        $siteId = SiteContext::getId();
        $contentPath = $this->getSlugForPage($slug);
        $cacheKey = 'page_path_v2_' . md5($contentPath . '_' . $siteId);

        return $this->cache->remember($cacheKey, $this->config['cache_duration'], function () use ($contentPath, $siteId) {
            $flatPage = $this->findFlatPage($contentPath, $siteId);
            if ($flatPage instanceof Page) {
                return $flatPage;
            }

            foreach ($this->contentPaths->resolveCandidates((int) $siteId, $contentPath) as $candidate) {
                $query = Page::with(['seo', 'blocks', 'categories', 'tags'])
                    ->where('slug', $candidate->slug);

                if ($siteId) {
                    $query->where('site_id', $siteId);
                }

                if ($candidate->pageType !== null) {
                    $query->where('page_type', $candidate->pageType);
                }

                $page = $query->first();
                if (!$page instanceof Page) {
                    continue;
                }

                if (!$this->pageMatchesResolvedPath($page, $candidate->categorySlug, $candidate->subcategorySlug)) {
                    continue;
                }

                return $page;
            }

            return null;
        });
    }

    private function findFlatPage(string $slug, ?int $siteId): ?Page
    {
        if ($slug === '' || str_contains($slug, '/')) {
            return null;
        }

        $query = Page::with(['seo', 'blocks', 'categories', 'tags'])
            ->where('slug', $slug);

        if ($siteId) {
            $query->where('site_id', $siteId);
        }

        $page = $query->first();

        return $page instanceof Page ? $page : null;
    }

    private function getSlugForPage(string $path): string
    {
        $site = SiteContext::get();
        $siteSlug = $site ? (string) $site->slug : '';

        if ($siteSlug === '') {
            return trim($path, '/');
        }

        // remove site slug from url
        $slug = preg_replace('#^' . preg_quote($siteSlug, '#') . '(/|$)#', '', trim($path, '/'));
        return trim((string) $slug, '/');
    }

    private function createPageResult(Page $page, string $requestedPath): UrlResolutionResult
    {
        $canonicalUrl = $this->getCanonicalUrl($page);
        $currentUrl = $this->config['base_url'] . '/' . $requestedPath;

        // Check for canonical redirect
        if ($canonicalUrl && $canonicalUrl !== $currentUrl) {
            return new UrlResolutionResult(
                type: 'redirect',
                redirectUrl: $canonicalUrl,
                statusCode: 301,
                reason: 'canonical'
            );
        }

        // Check trailing slash
        $trailingSlashRedirect = $this->checkTrailingSlash($requestedPath);
        if ($trailingSlashRedirect) {
            return $trailingSlashRedirect;
        }

        return new UrlResolutionResult(
            type: 'page',
            page: $page,
            canonicalUrl: $canonicalUrl,
            meta: [
                'title' => $page->title,
                'description' => $page->meta_description,
                'keywords' => $page->meta_keywords,
            ]
        );
    }

    private function createRedirectResult(Page $page, string $path): ?UrlResolutionResult
    {
        if (!$page->redirect_url) {
            return null;
        }

        $redirectUrl = $this->isAbsoluteUrl($page->redirect_url)
            ? $page->redirect_url
            : $this->config['base_url'] . '/' . ltrim($page->redirect_url, '/');

        return new UrlResolutionResult(
            type: 'redirect',
            redirectUrl: $redirectUrl,
            statusCode: $page->redirect_type ?: 301,
            reason: 'page_redirect'
        );
    }

    private function handleUnpublishedPage(Page $page, string $path): ?UrlResolutionResult
    {
        if (!$this->canViewUnpublished()) {
            return null;
        }

        return $this->createPageResult($page, $path);
    }

    private function getCanonicalUrl(Page $page): ?string
    {
        if ($page->seo?->canonical_url) {
            return $this->isAbsoluteUrl($page->seo->canonical_url)
                ? $page->seo->canonical_url
                : SiteContext::url(ltrim($page->seo->canonical_url, '/'));
        }

        $canonicalPath = $this->contentPaths->canonicalPathForPage($page);

        if ($this->config['force_trailing_slash'] && !str_ends_with($canonicalPath, '/')) {
            $canonicalPath .= '/';
        } elseif (!$this->config['force_trailing_slash'] && str_ends_with($canonicalPath, '/') && $canonicalPath !== '/') {
            $canonicalPath = rtrim($canonicalPath, '/');
        }

        return SiteContext::url($canonicalPath);
    }

    private function checkTrailingSlash(string $path): ?UrlResolutionResult
    {
        if (!$this->config['force_trailing_slash'] || $path === '/') {
            return null;
        }

        if (!str_ends_with($path, '/')) {
            return new UrlResolutionResult(
                type: 'redirect',
                redirectUrl: $this->config['base_url'] . $path . '/',
                statusCode: 301,
                reason: 'trailing_slash'
            );
        }

        return null;
    }

    private function normalizePath(string $path): string
    {
        $path = strtok($path, '?');
        $path = strtok($path, '#');
        //$path = '/' . ltrim($path, '/');
        $path = preg_replace('#/+#', '/', $path);

        return urldecode($path);
    }

    private function isAbsoluteUrl(string $url): bool
    {
        return preg_match('/^https?:\/\//', $url);
    }

    private function canViewUnpublished(): bool
    {
        return auth()->check() && auth()->user()->can('view_unpublished_pages');
    }

    /**
     * Execute the redirect
     */
    public function executeRedirect(UrlResolutionResult $result): void
    {
        if ($result->type !== 'redirect') {
            return;
        }

        http_response_code($result->statusCode);
        header('Location: ' . $result->redirectUrl);;

        // Optional: Add redirect reason header for debugging
        if (!empty($result->reason)) {
            header('X-Redirect-Reason: ' . $result->reason);
        }

        exit;
    }

    private function checkRelations(string $path): ?UrlResolutionResult
    {
        $slug = $this->getSlugForPage($path);

        if (empty($slug)) {
            return null;
        }

        $brand = Brand::where('slug', $slug)->first();

        if ($brand) {
            return new UrlResolutionResult(
                type: 'brand',
                redirectUrl: route('brand.show', ['slug' => $brand->slug]),
                entity: $brand
            );
        }

        $category = Category::where('slug', $slug)->first();

        if ($category) {
            return new UrlResolutionResult(
                type: 'category',
                redirectUrl: route('category.show', ['slug' => $category->slug]),
                entity: $category
            );
        }

        return null;
    }

    private function pageMatchesResolvedPath(Page $page, ?string $categorySlug, ?string $subcategorySlug): bool
    {
        if ($categorySlug === null && $subcategorySlug === null) {
            return true;
        }

        $categories = $page->categories ?? null;
        if (!$categories || !method_exists($categories, 'all')) {
            return false;
        }

        $slugs = [];
        foreach ($categories->all() as $category) {
            if ($category instanceof Category) {
                $slugs[] = (string) $category->slug;
            }
        }

        if ($categorySlug !== null && !in_array($categorySlug, $slugs, true)) {
            return false;
        }

        if ($subcategorySlug !== null && !in_array($subcategorySlug, $slugs, true)) {
            return false;
        }

        return true;
    }
}
