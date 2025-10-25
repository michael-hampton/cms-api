<?php

namespace App\Parsers;

use App\Framework\Support\SiteContext;
use App\Models\PageGrid;
use App\Models\Territory;

class PageGridRenderer
{
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
        $html = "<article class=\"page-card\">";

        // Image section
        if ($parsedData['showImage'] && !empty($item['image'])) {
            $html .= "<div class=\"page-card-image\">";
            $html .= "<a href=\"" . htmlspecialchars($item['url']) . "\">";

            $altText = htmlspecialchars($item['image']['alt'] ?: $item['title']);
            $html .= "<img src=\"" . htmlspecialchars($item['image']['src']) . "\" ";
            $html .= "alt=\"{$altText}\" loading=\"lazy\">";

            $html .= "</a>";

            // Badge overlay
            if (!empty($item['badge'])) {
                $badgeColor = htmlspecialchars($item['badge']['color']);
                $badgeText = htmlspecialchars($item['badge']['text']);
                $html .= "<span class=\"page-card-badge badge-{$badgeColor}\">{$badgeText}</span>";
            }

            $html .= "</div>";
        }

        // Content section
        $html .= "<div class=\"page-card-content\">";

        // Title
        $html .= "<h3 class=\"page-card-title\">";
        $html .= "<a href=\"" . htmlspecialchars($item['url']) . "\">" . htmlspecialchars($item['title']) . "</a>";
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
            $html .= "<p class=\"page-card-excerpt\">" . htmlspecialchars($item['excerpt']) . "</p>";
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
            foreach ($item['actions'] as $action) {
                $style = htmlspecialchars($action['style']);
                $url = htmlspecialchars($action['url']);
                $text = htmlspecialchars($action['text']);
                $html .= "<a href=\"{$url}\" class=\"btn btn-{$style}\">{$text}</a>";
            }
            $html .= "</div>";
        }

        $html .= "</div>"; // content
        $html .= "</article>"; // card

        return $html;
    }
}