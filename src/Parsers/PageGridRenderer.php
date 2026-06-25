<?php

namespace App\Parsers;

use App\Framework\Authorization\MemberAuth;
use App\Framework\Support\Logger;
use App\Framework\Support\SiteContext;
use App\Models\Page;
use App\Models\PageGrid;
use App\Models\PageMetadata;
use App\Models\Territory;
use App\Services\PublicContent\Slugs\PublicContentPathResolver;

class PageGridRenderer
{
    use PageGridToolbar;

    public function __construct(
        private readonly PublicContentPathResolver $paths,
    ) {
    }

    public function render(PageGrid $pageGrid, ?Territory $territory = null): string
    {
        $parsedData = $this->parsePageGrid($pageGrid, $territory);

        if ($pageGrid->layout === 'carousel') {
            return $this->generateCarouselHtml($parsedData);
        }

        return $this->generateGridHtml($parsedData);
    }

    private function parsePageGrid(PageGrid $pageGrid, ?Territory $territory = null): array
    {
        $items = $pageGrid->items ?? [];
        $cleanItems = [];

        foreach ($items as $item) {
            $slug = trim($item['slug'] ?? '');
            $url = $this->buildPageUrl($slug, $territory);

            $cleanItem = [
                'title' => trim($item['title'] ?? ''),
                'slug' => $slug,
                'excerpt' => trim($item['excerpt'] ?? ''),
                'image' => $this->parseImage($item['image'] ?? null),
                'badge' => $this->parseBadge($item['badge'] ?? null),
                'meta' => $this->parseMeta($item['meta'] ?? null),
                'features' => $this->parseFeatures($item['features'] ?? []),
                'actions' => $this->parseActions($item['actions'] ?? [], $url, $slug),
                'url' => $url,
            ];

            if (!empty($cleanItem['title'])) {
                $cleanItems[] = $cleanItem;
            }
        }

        return [
            'title' => trim($pageGrid->title ?? ''),
            'subtitle' => trim($pageGrid->subtitle ?? ''),
            'layout' => $pageGrid->layout ?? 'grid',
            'columns' => (int)($pageGrid->columns ?? 3),
            'showExcerpt' => (bool)($pageGrid->show_excerpt ?? true),
            'showImage' => (bool)($pageGrid->show_image ?? true),
            'showFeatures' => (bool)($pageGrid->show_features ?? true),
            'showActions' => (bool)($pageGrid->show_actions ?? true),
            'useHero' => (bool)($pageGrid->use_hero ?? false),
            'items' => $cleanItems,
            'item_count' => count($cleanItems),
            'grid_class' => $this->buildGridClass($pageGrid->layout ?? 'grid', (int)($pageGrid->columns ?? 3))
        ];
    }

    private function parseImage(?array $image): ?array
    {
        if (empty($image) || empty($image['src'])) {
            return null;
        }

        return [
            'src' => trim($image['src']),
            'alt' => trim($image['alt'] ?? ''),
            'title' => trim($image['title'] ?? '')
        ];
    }

    private function parseBadge(?array $badge): ?array
    {
        if (empty($badge) || empty($badge['text'])) {
            return null;
        }

        return [
            'text' => trim($badge['text']),
            'color' => trim($badge['color'] ?? 'primary')
        ];
    }

    private function parseMeta(?array $meta): ?array
    {
        if (empty($meta)) {
            return null;
        }

        $cleanMeta = [];

        if (!empty($meta['date'])) {
            $cleanMeta['date'] = trim($meta['date']);
        }

        if (!empty($meta['author'])) {
            $cleanMeta['author'] = trim($meta['author']);
        }

        if (!empty($meta['readTime'])) {
            $cleanMeta['readTime'] = trim($meta['readTime']);
        }

        return !empty($cleanMeta) ? $cleanMeta : null;
    }

    private function parseFeatures(array $features): array
    {
        return array_values(array_filter(array_map('trim', $features)));
    }

    private function parseActions(array $actions, string $defaultUrl, string $slug): array
    {
        $cleanActions = [];

        foreach ($actions as $action) {
            if (empty($action['text'])) {
                continue;
            }

            $url = trim((string) ($action['url'] ?? ''));
            if ($url === '' || $this->isLegacyGridPageUrl($url, $slug)) {
                $url = $defaultUrl;
            }

            $cleanActions[] = [
                'text' => trim($action['text']),
                'url' => $url,
                'style' => trim($action['style'] ?? 'primary')
            ];
        }

        return $cleanActions;
    }

    private function buildPageUrl(string $slug, ?Territory $territory): string
    {
        $site = SiteContext::get();
        $cleanSlug = trim($slug, '/');

        if (!$site) {
            return '/' . ltrim($cleanSlug, '/');
        }

        $page = $this->findPageForGridSlug($cleanSlug, (int) $site->id);
        $path = $page instanceof Page
            ? $this->paths->canonicalPathForPage($page)
            : $cleanSlug;

        if ($territory) {
            return '/' . rawurlencode((string) $site->slug) . '/' . rawurlencode((string) $territory->slug) . '/' . $this->encodePath($path);
        }

        return '/' . rawurlencode((string) $site->slug) . '/' . $this->encodePath($path);
    }

    private function findPageForGridSlug(string $slug, int $siteId): ?Page
    {
        $parts = explode('/', trim($slug, '/'));
        $actualSlug = end($parts);

        if (!is_string($actualSlug) || $actualSlug === '') {
            return null;
        }

        $page = Page::with(['categories'])
            ->where('slug', $actualSlug)
            ->where('site_id', $siteId)
            ->where('status', 'published')
            ->first();

        return $page instanceof Page ? $page : null;
    }

    private function encodePath(string $path): string
    {
        $segments = array_filter(explode('/', trim($path, '/')), static fn(string $segment): bool => $segment !== '');

        return implode('/', array_map(rawurlencode(...), $segments));
    }

    private function isExternalUrl(string $url): bool
    {
        return str_starts_with($url, 'http://')
            || str_starts_with($url, 'https://')
            || str_starts_with($url, 'mailto:')
            || str_starts_with($url, 'tel:');
    }

    private function isLegacyGridPageUrl(string $url, string $slug): bool
    {
        if ($this->isExternalUrl($url)) {
            return false;
        }

        $site = SiteContext::get();
        $cleanUrl = trim($url, '/');
        $cleanSlug = trim($slug, '/');

        if ($cleanUrl === '' || $cleanSlug === '') {
            return false;
        }

        $siteSlug = $site ? trim((string) $site->slug, '/') : null;

        $legacyCandidates = array_filter([
            $cleanSlug,
            $siteSlug ? $siteSlug . '/' . $cleanSlug : null,
        ]);

        return in_array($cleanUrl, $legacyCandidates, true);
    }
}
