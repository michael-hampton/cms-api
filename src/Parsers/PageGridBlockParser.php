<?php

namespace App\Parsers;

use App\Framework\Support\SiteContext;
use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\IntegerRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\MaxRule;
use App\Framework\Validation\Rules\MinLengthRule;
use App\Framework\Validation\Rules\MinRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Framework\Validation\Rules\UrlRule;

class PageGridBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'page_grid';
    }

    public function getValidationRules(): array
    {
        return [
            'pages.*.meta' => [
                new ArrayRule()
            ],
            'title' => [
                new MaxLengthRule(255)
            ],
            'subtitle' => [
                new MaxLengthRule(500)
            ],
            'layout' => [
                new MaxLengthRule(50)
            ],
            'columns' => [
                new IntegerRule(),
                new MinRule(1),
                new MaxRule(6)
            ],
            'showExcerpt' => [
                new BooleanRule()
            ],
            'showImage' => [
                new BooleanRule()
            ],
            'showFeatures' => [
                new BooleanRule()
            ],
            'showActions' => [
                new BooleanRule()
            ],
            'pages' => [
                //new RequiredRule(),
                new ArrayRule(),
                new MinRule(1) // At least one page required
            ],
            'pages.*.title' => [
                //new RequiredRule(),
                new MinLengthRule(2),
                new MaxLengthRule(255)
            ],
            'pages.*.slug' => [
                //new RequiredRule(),
                //new MinLengthRule(2),
                new MaxLengthRule(255)
            ],
            'pages.*.excerpt' => [
                new MaxLengthRule(500)
            ],
            'pages.*.image' => [
                new ArrayRule()
            ],
            'pages.*.image.src' => [
                new UrlRule(),
                new MaxLengthRule(500)
            ],
            'pages.*.image.alt' => [
                new MaxLengthRule(255)
            ],
            'pages.*.badge' => [
                new ArrayRule()
            ],
            'pages.*.badge.text' => [
                new MaxLengthRule(50)
            ],
            'pages.*.badge.color' => [
                new MaxLengthRule(20)
            ],
            'pages.*.price' => [
                new MaxLengthRule(50)
            ],
            'pages.*.location' => [
                new MaxLengthRule(255)
            ],
            'pages.*.features' => [
                new ArrayRule()
            ],
            'pages.*.features.*' => [
                new MaxLengthRule(100)
            ],
            'pages.*.actions' => [
                new ArrayRule()
            ],
            'pages.*.actions.*.text' => [
                new RequiredRule(),
                new MaxLengthRule(100)
            ],
            'pages.*.actions.*.url' => [
                new RequiredRule(),
                new MaxLengthRule(500)
            ],
            'pages.*.actions.*.style' => [
                new MaxLengthRule(50)
            ],
            'pages.*.actions.*.target' => [
                new MaxLengthRule(20)
            ]
        ];
    }

    public function parse(array $data): array
    {
        $pages = $data['pages'] ?? [];
        $cleanPages = [];

        foreach ($pages as $page) {
            $cleanPage = [
                'title' => trim($page['title'] ?? ''),
                'meta' => $this->parseMeta($page['meta'] ?? null),
                'formatted_title' => htmlspecialchars($page['title'] ?? ''),
                'slug' => trim($page['slug'] ?? ''),
                'excerpt' => trim($page['excerpt'] ?? ''),
                'formatted_excerpt' => htmlspecialchars($page['excerpt'] ?? ''),
                'image' => $this->parseImage($page['image'] ?? null),
                'badge' => $this->parseBadge($page['badge'] ?? null),
                'price' => trim($page['price'] ?? ''),
                'location' => trim($page['location'] ?? ''),
                'features' => $this->parseFeatures($page['features'] ?? []),
                'actions' => $this->parseActions($page['actions'] ?? []),
                'url' => $this->buildPageUrl($page['slug'] ?? ''),
                'word_count' => str_word_count($page['excerpt'] ?? ''),
            ];

            if (!empty($cleanPage['title']) && !empty($cleanPage['slug'])) {
                $cleanPages[] = $cleanPage;
            }
        }

        return [
            'title' => trim($data['title'] ?? ''),
            'subtitle' => trim($data['subtitle'] ?? ''),
            'layout' => $data['layout'] ?? 'grid',
            'columns' => (int)($data['columns'] ?? 3),
            'showExcerpt' => (bool)($data['showExcerpt'] ?? true),
            'showImage' => (bool)($data['showImage'] ?? true),
            'showFeatures' => (bool)($data['showFeatures'] ?? true),
            'showActions' => (bool)($data['showActions'] ?? true),
            'pages' => $cleanPages,
            'page_count' => count($cleanPages),
            'has_images' => $this->hasImages($cleanPages),
            'has_badges' => $this->hasBadges($cleanPages),
            'has_prices' => $this->hasPrices($cleanPages),
            'total_features' => $this->countTotalFeatures($cleanPages),
            'grid_class' => $this->buildGridClass($data['layout'] ?? 'grid', (int)($data['columns'] ?? 3))
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
            if (is_array($meta['author'])) {
                $cleanMeta['authors'] = array_map('trim', $meta['author']);
            } else {
                $cleanMeta['author'] = trim($meta['author']);
            }
        }

        if (!empty($meta['authors']) && is_array($meta['authors'])) {
            $cleanMeta['authors'] = array_map('trim', $meta['authors']);
        }

        if (!empty($meta['category'])) {
            $cleanMeta['category'] = trim($meta['category']);
        }

        if (!empty($meta['readTime'])) {
            $cleanMeta['readTime'] = trim($meta['readTime']);
        }

        return !empty($cleanMeta) ? $cleanMeta : null;
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
            'color' => trim($badge['color'] ?? 'primary'),
            'background' => trim($badge['background'] ?? '')
        ];
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
                    'style' => trim($action['style'] ?? 'primary'),
                    'target' => trim($action['target'] ?? '_self'),
                    'rel' => $this->buildRelAttribute($action)
                ];
            }
        }

        return $cleanActions;
    }

    private function buildRelAttribute(array $action): string
    {
        $rel = [];

        if (($action['target'] ?? '') === '_blank') {
            $rel[] = 'noopener';
        }

        if (!empty($action['nofollow'])) {
            $rel[] = 'nofollow';
        }

        if (!empty($action['sponsored'])) {
            $rel[] = 'sponsored';
        }

        return implode(' ', $rel);
    }

    private function buildPageUrl(string $slug): string
    {

        $site = SiteContext::get();

        if ($site) {
            return $site->getUrl() . '/' . ltrim($slug, '/');
        }

        return '/' . ltrim($slug, '/');
    }

    private function hasImages(array $pages): bool
    {
        foreach ($pages as $page) {
            if (!empty($page['image'])) {
                return true;
            }
        }
        return false;
    }

    private function hasBadges(array $pages): bool
    {
        foreach ($pages as $page) {
            if (!empty($page['badge'])) {
                return true;
            }
        }
        return false;
    }

    private function hasPrices(array $pages): bool
    {
        foreach ($pages as $page) {
            if (!empty($page['price'])) {
                return true;
            }
        }
        return false;
    }

    private function countTotalFeatures(array $pages): int
    {
        $count = 0;
        foreach ($pages as $page) {
            $count += count($page['features']);
        }
        return $count;
    }

    private function buildGridClass(string $layout, int $columns): string
    {
        $baseClass = "page-grid-{$layout}";

        if ($layout === 'grid') {
            $baseClass .= " columns-{$columns}";
        }

        return $baseClass;
    }

    public function generateHtml(array $parsedData): string
    {
        if ($parsedData['layout'] === 'carousel') {
            return $this->generateCarouselHtml($parsedData);
        }

        return $this->generateBlock($parsedData);
    }

    public function generateBlock(array $parsedData): string
    {
        $html = "<div class=\"page-grid-block {$parsedData['grid_class']}\">";

        // Header section
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

        // Grid container
        $html .= "<div class=\"page-grid-container\">";

        foreach ($parsedData['pages'] as $page) {
            $html .= $this->generatePageCard($page, $parsedData);
        }

        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }

    public function generateCarouselHtml(array $parsedData): string
    {
        $html = "<div class=\"page-grid-block\">";

        if (!empty($parsedData['title'])) {
            $html .= "<h2 class=\"page-grid-title\">{$parsedData['formatted_title']}</h2>";
        }

        if (!empty($page['meta'])) {
            $html .= "<div class=\"page-card-meta\">";

            if (!empty($page['meta']['date'])) {
                $html .= "<span class=\"page-card-meta-item\">" . htmlspecialchars($page['meta']['date']) . "</span>";
            }

            if (!empty($page['meta']['author'])) {
                $html .= "<span class=\"page-card-meta-item\">" . htmlspecialchars($page['meta']['author']) . "</span>";
            }

            if (!empty($page['meta']['category'])) {
                $html .= "<span class=\"page-card-meta-item\">" . htmlspecialchars($page['meta']['category']) . "</span>";
            }

            if (!empty($page['meta']['readTime'])) {
                $html .= "<span class=\"page-card-meta-item\">" . htmlspecialchars($page['meta']['readTime']) . "</span>";
            }

            $html .= "</div>";
        }

        if (!empty($parsedData['subtitle'])) {
            $html .= "<p class=\"page-grid-subtitle\">{$parsedData['formatted_subtitle']}</p>";
        }

        $showCarousel = false;
        $class = $showCarousel ? 'page-grid-carousel' : 'page-grid-carousel';

        // Carousel wrapper
        $html .= "<div class=\"{$class}\">";

        if ($showCarousel) {
            // Navigation buttons
            $html .= "<div class=\"page-grid-nav prev\">";
            $html .= "<button class=\"page-grid-nav-btn\" onclick=\"scrollPageGrid(this, 'prev')\" aria-label=\"Previous\">&larr;</button>";
            $html .= "</div>";

            $html .= "<div class=\"page-grid-nav next\">";
            $html .= "<button class=\"page-grid-nav-btn\" onclick=\"scrollPageGrid(this, 'next')\" aria-label=\"Next\">&rarr;</button>";
            $html .= "</div>";
        }


        // Grid container
        $html .= "<div class=\"page-grid\" data-page-grid>";

        foreach ($parsedData['pages'] as $page) {
            $url = $this->buildPageUrl($page['slug']);

            $html .= "<div class=\"page-card\">";

            if ($parsedData['showImage'] && !empty($page['image'])) {
                $html .= "<div class=\"page-card-image\">";
                $html .= $this->addLink("<img src=\"{$page['image']['src']}\" alt=\"{$page['image']['alt']}\">", $page['slug']);;

                if (!empty($page['badge'])) {
                    $badgeColor = $page['badge']['color'] ?? 'primary';
                    $html .= "<span class=\"page-card-badge badge-{$badgeColor}\">{$page['badge']['text']}</span>";
                }

                $html .= "</div>";
            }

            $html .= "<div class=\"page-card-content\">";

            $html .= $this->addLink("<h3 class=\"page-card-title\">{$page['formatted_title']}</h3>", $page['slug']);

            if ($parsedData['showExcerpt'] && !empty($page['excerpt'])) {
                $html .= $this->addLink("<p class=\"page-card-excerpt\">{$page['formatted_excerpt']}</p>", $page['slug']);
            }

            if ($parsedData['showFeatures'] && !empty($page['features'])) {
                $html .= "<div class=\"page-card-features\">";
                foreach ($page['features'] as $feature) {
                    $html .= "<span class=\"page-card-feature\">" . htmlspecialchars($feature) . "</span>";
                }
                $html .= "</div>";
            }

            if ($parsedData['showActions'] && !empty($page['actions'])) {
                $html .= "<div class=\"page-card-actions\">";
                foreach ($page['actions'] as $action) {
                    $style = $action['style'] ?? 'primary';
                    $html .= "<a href=\"{$action['url']}\" class=\"page-card-action style-{$style}\">{$action['text']}</a>";
                }
                $html .= "</div>";
            }

            $html .= "</div>"; // page-card-content

            if(!empty($url)) {
                $html .= "</a>";
            }

            $html .= "</div>"; // page-card
        }

        $html .= "</div>"; // page-grid

        // Indicators
        $pageCount = count($parsedData['pages']);
        if ($pageCount > 1 && $showCarousel) {
            $html .= "<div class=\"page-grid-indicators\">";
            for ($i = 0; $i < $pageCount; $i++) {
                $activeClass = $i === 0 ? ' active' : '';
                $html .= "<button class=\"page-grid-indicator{$activeClass}\" onclick=\"scrollPageGridToIndex(this, {$i})\" aria-label=\"Go to item " . ($i + 1) . "\"></button>";
            }
            $html .= "</div>";
        }

        $html .= "</div>"; // page-grid-carousel
        $html .= "</div>"; // page-grid-block

        return $html;
    }

    private function addLink(string $html, string $slug) {
        $original = $html;
        $url = $this->buildPageUrl($slug);

        if(!empty($url)) {
            $html = "<a href=\"{$url}\">{$original}</a>";
        }

        return $html;
    }

    private function generatePageCard(array $page, array $parsedData): string
    {
        $html = "<div class=\"page-card\">";

        // Image section
        if ($parsedData['showImage'] && !empty($page['image'])) {
            $html .= "<div class=\"page-image\">";

            $altText = htmlspecialchars($page['image']['alt'] ?: $page['title']);
            $titleText = htmlspecialchars($page['image']['title'] ?: $page['title']);

            $html .= "<img src=\"" . htmlspecialchars($page['image']['src']) . "\" ";
            $html .= "alt=\"{$altText}\" ";
            $html .= "title=\"{$titleText}\" ";
            $html .= "loading=\"lazy\">";

            // Badge overlay
            if (!empty($page['badge'])) {
                $html .= "<div class=\"page-badge badge-{$page['badge']['color']}\">" . htmlspecialchars($page['badge']['text']) . "</div>";
            }

            // Price overlay
            if (!empty($page['price'])) {
                $html .= "<div class=\"page-price\">" . htmlspecialchars($page['price']) . "</div>";
            }

            $html .= "</div>";
        }

        // Content section
        $html .= "<div class=\"page-content\">";

        // Title
        $html .= "<h3 class=\"page-title\">";
        $html .= "<a href=\"" . htmlspecialchars($page['url']) . "\">" . htmlspecialchars($page['title']) . "</a>";
        $html .= "</h3>";

        // Meta information
        if (!empty($page['meta'])) {
            $html .= "<div class=\"page-meta\">";

            if (!empty($page['meta']['date'])) {
                $html .= "<span class=\"page-meta-item page-meta-date\">📅 " . htmlspecialchars($page['meta']['date']) . "</span>";
            }

            if (!empty($page['meta']['authors']) && is_array($page['meta']['authors'])) {
                $authorNames = array_map('htmlspecialchars', $page['meta']['authors']);
                $html .= "<span class=\"page-meta-item page-meta-authors\">✍️ " . implode(', ', $authorNames) . "</span>";
            } elseif (!empty($page['meta']['author'])) {
                $html .= "<span class=\"page-meta-item page-meta-author\">✍️ " . htmlspecialchars($page['meta']['author']) . "</span>";
            }

            if (!empty($page['meta']['category'])) {
                $html .= "<span class=\"page-meta-item page-meta-category\">🏷️ " . htmlspecialchars($page['meta']['category']) . "</span>";
            }

            if (!empty($page['meta']['readTime'])) {
                $html .= "<span class=\"page-meta-item page-meta-read-time\">⏱️ " . htmlspecialchars($page['meta']['readTime']) . "</span>";
            }

            $html .= "</div>";
        }

        // Location
        if (!empty($page['location'])) {
            $html .= "<div class=\"page-location\">📍 " . htmlspecialchars($page['location']) . "</div>";
        }

        // Excerpt
        if ($parsedData['showExcerpt'] && !empty($page['excerpt'])) {
            $html .= "<div class=\"page-excerpt\">" . htmlspecialchars($page['excerpt']) . "</div>";
        }

        // Features
        if ($parsedData['showFeatures'] && !empty($page['features'])) {
            $html .= "<div class=\"page-features\">";
            foreach ($page['features'] as $feature) {
                $html .= "<span class=\"page-feature\">" . htmlspecialchars($feature) . "</span>";
            }
            $html .= "</div>";
        }

        // Actions
        if ($parsedData['showActions'] && !empty($page['actions'])) {
            $html .= "<div class=\"page-actions\">";
            foreach ($page['actions'] as $action) {
                $relAttr = !empty($action['rel']) ? " rel=\"{$action['rel']}\"" : '';
                $targetAttr = $action['target'] !== '_self' ? " target=\"{$action['target']}\"" : '';

                $html .= "<a href=\"" . htmlspecialchars($action['url']) . "\" class=\"btn btn-{$action['style']}\"{$targetAttr}{$relAttr}>";
                $html .= htmlspecialchars($action['text']);
                $html .= "</a>";
            }
            $html .= "</div>";
        }

        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }
}