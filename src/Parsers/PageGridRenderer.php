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

    private function buildGridClass(string $layout, int $columns): string
    {
        $baseClass = "page-grid-{$layout}";

        if ($layout === 'grid') {
            $baseClass .= " columns-{$columns}";
        }

        return $baseClass;
    }

    private function generateGridHtml(array $parsedData): string
    {
        $html = "<div class=\"page-grid-block {$parsedData['grid_class']}\">";

        if (!empty($parsedData['title']) || !empty($parsedData['subtitle'])) {
            if ($parsedData['useHero']) {
                $html .= "<div class=\"page-grid-hero\">";

                if (!empty($parsedData['title'])) {
                    $html .= "<h1 class=\"page-grid-hero-title\">" . htmlspecialchars($parsedData['title']) . "</h1>";
                }

                if (!empty($parsedData['subtitle'])) {
                    $html .= "<p class=\"page-grid-hero-subtitle\">" . htmlspecialchars($parsedData['subtitle']) . "</p>";
                }

                $html .= "</div>";
            } else {
                $html .= "<div class=\"page-grid-header\">";

                if (!empty($parsedData['title'])) {
                    $html .= "<h2 class=\"page-grid-title\">" . htmlspecialchars($parsedData['title']) . "</h2>";
                }

                if (!empty($parsedData['subtitle'])) {
                    $html .= "<p class=\"page-grid-subtitle\">" . htmlspecialchars($parsedData['subtitle']) . "</p>";
                }

                $html .= "</div>";
            }
        }

        $html .= "<div class=\"page-grid-container\">";

        foreach ($parsedData['items'] as $item) {
            $html .= $this->generateItemCard($item, $parsedData);
        }

        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }

    private function generateCarouselHtml(array $parsedData): string
    {
        $html = "<div class=\"page-grid-block page-grid-carousel-wrapper\">";

        if (!empty($parsedData['title']) || !empty($parsedData['subtitle'])) {
            $html .= "<div class=\"page-grid-header\">";

            if (!empty($parsedData['title'])) {
                $html .= "<h2 class=\"page-grid-title\">" . htmlspecialchars($parsedData['title']) . "</h2>";
            }

            if (!empty($parsedData['subtitle'])) {
                $html .= "<p class=\"page-grid-subtitle\">" . htmlspecialchars($parsedData['subtitle']) . "</p>";
            }

            $html .= "</div>";
        }

        $html .= "<div class=\"page-grid-carousel\">";
        $html .= "<button class=\"page-grid-nav-btn prev\" onclick=\"scrollPageGrid(this, 'prev')\" aria-label=\"Previous\">‹</button>";
        $html .= "<button class=\"page-grid-nav-btn next\" onclick=\"scrollPageGrid(this, 'next')\" aria-label=\"Next\">›</button>";
        $html .= "<div class=\"page-grid-track\" data-page-grid-track>";

        foreach ($parsedData['items'] as $item) {
            $html .= $this->generateItemCard($item, $parsedData);
        }

        $html .= "</div>";

        $itemCount = count($parsedData['items']);
        if ($itemCount > 1) {
            $html .= "<div class=\"page-grid-indicators\">";
            for ($i = 0; $i < $itemCount; $i++) {
                $activeClass = $i === 0 ? ' active' : '';
                $html .= "<button class=\"page-grid-indicator{$activeClass}\" onclick=\"scrollPageGridToIndex(this, {$i})\" aria-label=\"Go to item " . ($i + 1) . "\"></button>";
            }
            $html .= "</div>";
        }

        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }

    private function generateItemCard(array $item, array $parsedData): string
    {
        $isPrivate = $this->isPagePrivate($item['slug']);
        $isLoggedIn = MemberAuth::check();

        $html = "<article class=\"page-card" . ($isPrivate && !$isLoggedIn ? " page-card-private" : "") . "\">";

        if ($parsedData['showImage'] && !empty($item['image'])) {
            $html .= "<div class=\"page-card-image\">";

            if ($isPrivate && !$isLoggedIn) {
                $html .= "<div class=\"private-overlay\"></div>";
                $html .= "<div class=\"private-badge\">🔒 Members Only</div>";
            } else {
                $html .= "<a href=\"" . htmlspecialchars($item['url']) . "\">";
            }

            $altText = htmlspecialchars($item['image']['alt'] ?: $item['title']);
            $html .= "<img src=\"" . htmlspecialchars($item['image']['src']) . "\" ";
            $html .= "alt=\"{$altText}\" loading=\"lazy\">";

            if (!$isPrivate || $isLoggedIn) {
                $html .= "</a>";
            }

            if (!empty($item['badge'])) {
                $badgeColor = htmlspecialchars($item['badge']['color']);
                $badgeText = htmlspecialchars($item['badge']['text']);
                $html .= "<span class=\"page-card-badge badge-{$badgeColor}\">{$badgeText}</span>";
            }

            $html .= "</div>";
        }

        if (!$isPrivate || $isLoggedIn) {
            $html .= $this->generateToolbar();
        }

        $html .= "<div class=\"page-card-content" . ($isPrivate && !$isLoggedIn ? " page-content-faded" : "") . "\">";
        $html .= "<h3 class=\"page-card-title\">";

        if ($isPrivate && !$isLoggedIn) {
            $html .= htmlspecialchars($item['title']);
        } else {
            $html .= "<a href=\"" . htmlspecialchars($item['url']) . "\">" . htmlspecialchars($item['title']) . "</a>";
        }

        $html .= "</h3>";

        if (!empty($item['meta'])) {
            $html .= "<div class=\"page-card-meta\">";

            if (!empty($item['meta']['date'])) {
                $html .= "<span class=\"page-card-meta-item\">" . htmlspecialchars($item['meta']['date']) . "</span>";
            }

            if (!empty($item['meta']['author'])) {
                $html .= "<span class=\"page-card-meta-item\">" . htmlspecialchars($item['meta']['author']) . "</span>";
            }

            if (!empty($item['meta']['readTime'])) {
                $html .= "<span class=\"page-card-meta-item\">" . htmlspecialchars($item['meta']['readTime']) . "</span>";
            }

            $html .= "</div>";
        }

        if ($parsedData['showExcerpt'] && !empty($item['excerpt'])) {
            $excerptClass = ($isPrivate && !$isLoggedIn) ? "page-card-excerpt page-excerpt-faded" : "page-card-excerpt";
            $html .= "<p class=\"{$excerptClass}\">" . htmlspecialchars($item['excerpt']) . "</p>";
        }

        if ($parsedData['showFeatures'] && !empty($item['features'])) {
            $html .= "<ul class=\"page-card-features\">";
            foreach ($item['features'] as $feature) {
                $html .= "<li class=\"page-card-feature\">" . htmlspecialchars($feature) . "</li>";
            }
            $html .= "</ul>";
        }

        if ($parsedData['showActions'] && !empty($item['actions'])) {
            $html .= "<div class=\"page-card-actions\">";

            if ($isPrivate && !$isLoggedIn) {
                $html .= "<button class=\"btn btn-primary btn-subscribe-required\" onclick=\"showSubscriptionModal()\">";
                $html .= "<svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\">";
                $html .= "<rect x=\"3\" y=\"11\" width=\"18\" height=\"11\" rx=\"2\" ry=\"2\"/>";
                $html .= "<path d=\"M7 11V7a5 5 0 0 1 10 0v4\"/>";
                $html .= "</svg>";
                $html .= "Subscribe to Access";
                $html .= "</button>";
            } else {
                foreach ($item['actions'] as $action) {
                    $style = htmlspecialchars($action['style']);
                    $url = htmlspecialchars($action['url']);
                    $text = htmlspecialchars($action['text']);
                    $html .= "<a href=\"{$url}\" class=\"btn btn-{$style}\">{$text}</a>";
                }
            }

            $html .= "</div>";
        }

        $html .= "</div>";
        $html .= "</article>";

        return $html;
    }

    private function isPagePrivate(string $slug): bool
    {
        try {
            $siteId = SiteContext::getId();
            $cleanSlug = trim($slug, '/');
            $parts = explode('/', $cleanSlug);
            $actualSlug = end($parts);

            $page = Page::where('slug', $actualSlug)
                ->where('site_id', $siteId)
                ->first();

            if (!$page) {
                return false;
            }

            $metadata = PageMetadata::where('page_id', $page->id)->first();

            if (!$metadata) {
                return false;
            }

            return $metadata->visibility === 'private';
        } catch (\Exception $e) {
            Logger::error('Error checking page privacy: ' . $e->getMessage());
            return false;
        }
    }
}
