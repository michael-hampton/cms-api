<?php

namespace App\Services\Url;

use App\Framework\Support\Cache;
use App\Framework\Support\SiteContext;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;

class DynamicUrlResolver implements UrlResolverInterface
{
    public function __construct(
        private readonly Cache $cache,
        private array $config = []
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

            if ($site->url_handle) {
                $page = Page::where('slug', $site->url_handle)
                    ->where('site_id', SiteContext::getId())
                    ->first();
                $path = SiteContext::slug() . '/' . $page->slug;
                return $this->createPageResult($page, $path);
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
        $cacheKey = 'page_slug_' . md5($slug . '_' . SiteContext::getId());

        $slug = $this->getSlugForPage($slug);

        return $this->cache->remember($cacheKey, $this->config['cache_duration'], function () use ($slug) {
            $query = Page::with(['seo', 'blocks', 'categories', 'tags'])
                ->where('slug', $slug);

            // Filter by current site
            $siteId = SiteContext::getId();
            if ($siteId) {
                $query->where('site_id', $siteId);
            }

            return $query->first();
        });
    }

    private function getSlugForPage(string $path): string
    {
        $siteSlug = SiteContext::get()->slug;

        // remove site slug from url
        $slug = str_replace($siteSlug, '', $path);
        return trim($slug, '/');
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

        $canonicalPath = ltrim($page->slug, '/');

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
}