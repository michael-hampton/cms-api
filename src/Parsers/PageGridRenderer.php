<?php

namespace App\Parsers;

use App\Framework\Authorization\MemberAuth;
use App\Framework\Support\Logger;
use App\Framework\Support\SiteContext;
use App\Models\Page;
use App\Models\PageGrid;
use App\Models\PageMetadata;
use App\Models\Territory;

class PageGridRenderer
{
    public function render(PageGrid $pageGrid, ?Territory $territory = null): string
    {
        $parsedData = $this->parsePageGrid($pageGrid, $territory);

        $html = '';

        if ($pageGrid->layout === 'carousel') {
            $html = $this->generateCarouselHtml($parsedData);
        } else {
            $html = $this->generateGridHtml($parsedData);
        }

        // Add private page styles
        $html .= $this->getPrivatePageStyles();

        return $html;
    }

    private function getPrivatePageStyles(): string
    {
        return "
        <style>
            .page-card-private {
                position: relative;
            }

            .page-card-image {
                position: relative;
            }

            .private-overlay {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(255, 255, 255, 0.6);
                backdrop-filter: blur(4px);
                z-index: 1;
            }

            .private-badge {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: linear-gradient(135deg, #667eea, #764ba2);
                color: white;
                padding: 0.75rem 1.5rem;
                border-radius: 2rem;
                font-weight: 700;
                font-size: 0.875rem;
                z-index: 2;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                white-space: nowrap;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }

            .page-content-faded {
                position: relative;
            }

            .page-excerpt-faded {
                max-height: 3em;
                overflow: hidden;
                position: relative;
            }

            .page-excerpt-faded::after {
                content: '';
                position: absolute;
                bottom: 0;
                left: 0;
                right: 0;
                height: 2em;
                background: linear-gradient(to bottom, transparent, white);
            }

            .btn-subscribe-required {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
                width: 100%;
                padding: 0.875rem 1.5rem;
                background: linear-gradient(135deg, #667eea, #764ba2);
                color: white;
                border: none;
                border-radius: 0.5rem;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
            }

            .btn-subscribe-required:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
            }

            .btn-subscribe-required svg {
                width: 16px;
                height: 16px;
                flex-shrink: 0;
            }
        </style>
        ";
    }

    private function parsePageGrid(PageGrid $pageGrid, ?Territory $territory = null): array
    {
        $items = $pageGrid->items ?? [];
        $cleanItems = [];

        foreach ($items as $item) {
            $cleanItem = [
                'title' => trim($item['title'] ?? ''),
                'slug' => trim($item['slug'] ?? ''),
                'excerpt' => trim($item['excerpt'] ?? ''),
                'image' => $this->parseImage($item['image'] ?? null),
                'badge' => $this->parseBadge($item['badge'] ?? null),
                'meta' => $this->parseMeta($item['meta'] ?? null),
                'features' => $this->parseFeatures($item['features'] ?? []),
                'actions' => $this->parseActions($item['actions'] ?? []),
                'url' => $this->buildPageUrl($item['slug'] ?? '', $territory),
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

    private function parseActions(array $actions): array
    {
        $cleanActions = [];

        foreach ($actions as $action) {
            if (!empty($action['text']) && !empty($action['url'])) {
                $cleanActions[] = [
                    'text' => trim($action['text']),
                    'url' => trim($action['url']),
                    'style' => trim($action['style'] ?? 'primary')
                ];
            }
        }

        return $cleanActions;
    }

    private function buildPageUrl(string $slug, ?Territory $territory): string
    {
        $site = SiteContext::get();

        if (!$site) {
            return '/' . ltrim($slug, '/');
        }

        if ($territory) {
            return '/' . $site->slug . '/' . $territory->slug . '/' . ltrim($slug, '/');
        }

        return '/' . $site->slug . '/' . ltrim($slug, '/');
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

        // Header section
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

        // Grid container
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

        // Header
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

        // Carousel wrapper
        $html .= "<div class=\"page-grid-carousel\">";

        // Navigation buttons
        $html .= "<button class=\"page-grid-nav-btn prev\" onclick=\"scrollPageGrid(this, 'prev')\" aria-label=\"Previous\">‹</button>";
        $html .= "<button class=\"page-grid-nav-btn next\" onclick=\"scrollPageGrid(this, 'next')\" aria-label=\"Next\">›</button>";

        // Carousel track
        $html .= "<div class=\"page-grid-track\" data-page-grid-track>";

        foreach ($parsedData['items'] as $item) {
            $html .= $this->generateItemCard($item, $parsedData);
        }

        $html .= "</div>";

        // Indicators
        $itemCount = count($parsedData['items']);
        if ($itemCount > 1) {
            $html .= "<div class=\"page-grid-indicators\">";
            for ($i = 0; $i < $itemCount; $i++) {
                $activeClass = $i === 0 ? ' active' : '';
                $html .= "<button class=\"page-grid-indicator{$activeClass}\" onclick=\"scrollPageGridToIndex(this, {$i})\" aria-label=\"Go to item " . ($i + 1) . "\"></button>";
            }
            $html .= "</div>";
        }

        $html .= "</div>"; // carousel
        $html .= "</div>"; // block

        return $html;
    }

    private function generateItemCard(array $item, array $parsedData): string
    {
        // Check if page is private
        $isPrivate = $this->isPagePrivate($item['slug']);
        $isLoggedIn = MemberAuth::check();

        $html = "<article class=\"page-card" . ($isPrivate && !$isLoggedIn ? " page-card-private" : "") . "\">";

        // Image section
        if ($parsedData['showImage'] && !empty($item['image'])) {
            $html .= "<div class=\"page-card-image\">";

            // Add overlay for private content
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

            // Badge overlay
            if (!empty($item['badge'])) {
                $badgeColor = htmlspecialchars($item['badge']['color']);
                $badgeText = htmlspecialchars($item['badge']['text']);
                $html .= "<span class=\"page-card-badge badge-{$badgeColor}\">{$badgeText}</span>";
            }

            $html .= "</div>";
        }

        // Content section
        $html .= "<div class=\"page-card-content" . ($isPrivate && !$isLoggedIn ? " page-content-faded" : "") . "\">";

        // Title
        $html .= "<h3 class=\"page-card-title\">";
        if ($isPrivate && !$isLoggedIn) {
            $html .= htmlspecialchars($item['title']);
        } else {
            $html .= "<a href=\"" . htmlspecialchars($item['url']) . "\">" . htmlspecialchars($item['title']) . "</a>";
        }
        $html .= "</h3>";

        // Meta information
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

        // Excerpt
        if ($parsedData['showExcerpt'] && !empty($item['excerpt'])) {
            $excerptClass = ($isPrivate && !$isLoggedIn) ? "page-card-excerpt page-excerpt-faded" : "page-card-excerpt";
            $html .= "<p class=\"{$excerptClass}\">" . htmlspecialchars($item['excerpt']) . "</p>";
        }

        // Features
        if ($parsedData['showFeatures'] && !empty($item['features'])) {
            $html .= "<ul class=\"page-card-features\">";
            foreach ($item['features'] as $feature) {
                $html .= "<li class=\"page-card-feature\">" . htmlspecialchars($feature) . "</li>";
            }
            $html .= "</ul>";
        }

        // Actions
        if ($parsedData['showActions'] && !empty($item['actions'])) {
            $html .= "<div class=\"page-card-actions\">";

            if ($isPrivate && !$isLoggedIn) {
                // Show subscription required button
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

        $html .= "</div>"; // content
        $html .= "</article>"; // card

        return $html;
    }

    /**
     * Check if a page is private by fetching its metadata
     */
    private function isPagePrivate(string $slug): bool
    {
        try {
            $siteId = SiteContext::getId();

            // Remove any leading slashes or site slug from the path
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