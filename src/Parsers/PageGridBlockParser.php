<?php

namespace App\Parsers;

use App\Framework\Authorization\MemberAuth;
use App\Framework\Support\Logger;
use App\Framework\Support\SiteContext;
use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\IntegerRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\MaxRule;
use App\Framework\Validation\Rules\MinLengthRule;
use App\Framework\Validation\Rules\MinRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Models\Product;
use App\Models\Wishlist;
use App\Repositories\PageRepository;
use App\Services\BuildProductCardService;

class PageGridBlockParser extends BaseBlockParser
{
    use PageGridToolbar;

    public function __construct(private readonly PageRepository $pageRepository)
    {

    }

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
                //new UrlRule(),
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
                'is_private' => $page['is_private'] ?? false,
            ];

            // Check if this is a product and fetch full product data
            if (!empty($cleanPage['price'])) {
                $productData = $this->fetchProductData($cleanPage['slug']);
                if ($productData) {
                    $cleanPage['product_data'] = $productData;
                    $cleanPage['is_product'] = true;
                }
            }

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
            'grid_class' => $this->buildGridClass($data['layout'] ?? 'grid', (int)($data['columns'] ?? 3)),
            'button' => $data['button'] ?? null,
        ];
    }

    private function parseMeta(?array $meta): ?array
    {
        if (empty($meta)) {
            return null;
        }

        $cleanMeta = [];

        foreach ($meta as $key => $value) {
            if (is_array($value)) {
                $cleanMeta[$key] = array_map('trim', $value);
                continue;
            }

            $cleanMeta[$key] = trim($value);
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

    private function fetchProductData(string $slug): ?array
    {
        try {
            $slug = str_replace('shop/details/', '', $slug);

            $product = Product::where('slug', $slug)->first();

            if (!$product) {
                return null;
            }

            return (new BuildProductCardService())->build($product->id);
        } catch (\Exception $e) {
            Logger::error('Error fetching product data: ' . $e->getMessage());
            return null;
        }
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

            if (!empty($parsedData['button'])) {
                $html .= "<a href=\"{$parsedData['button']['url']}\" class=\"page-grid-button button button-primary\">{$parsedData['button']['text']}</a>";
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
            $html .= "<h2 class=\"page-grid-title\">" . htmlspecialchars($parsedData['title']) . "</h2>";
        }

        if (!empty($parsedData['subtitle'])) {
            $html .= "<p class=\"page-grid-subtitle\">" . htmlspecialchars($parsedData['subtitle']) . "</p>";
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
            // Check if page is private
            $isPrivate = $this->isPagePrivate($page['slug']);
            $isLoggedIn = MemberAuth::check();

            $html .= "<div class=\"page-card" . ($isPrivate && !$isLoggedIn ? " page-card-private" : "") . "\">";

            if ($parsedData['showImage'] && !empty($page['image'])) {
                $html .= "<div class=\"page-card-image\">";

                // Add overlay for private content
                if ($isPrivate && !$isLoggedIn) {
                    $html .= "<div class=\"private-overlay\"></div>";
                    $html .= "<div class=\"private-badge\">🔒 Members Only</div>";
                }

                $html .= "<img src=\"{$page['image']['src']}\" alt=\"{$page['image']['alt']}\">";

                if (!empty($page['badge'])) {
                    $badgeColor = $page['badge']['color'] ?? 'primary';
                    $html .= "<span class=\"page-card-badge badge-{$badgeColor}\">{$page['badge']['text']}</span>";
                }

                $html .= "</div>";
            }

            if (!$isPrivate || $isLoggedIn) {
                $html .= $this->generateToolbar();
            }

            $html .= "<div class=\"page-card-content" . ($isPrivate && !$isLoggedIn ? " page-content-faded" : "") . "\">";

            // Title
            $html .= "<h3 class=\"page-card-title\">";
            if ($isPrivate && !$isLoggedIn) {
                $html .= htmlspecialchars($page['title']);
            } else {
                $html .= $this->addLink(htmlspecialchars($page['title']), $page['slug']);
            }
            $html .= "</h3>";

            if ($parsedData['showExcerpt'] && !empty($page['excerpt'])) {
                $excerptClass = ($isPrivate && !$isLoggedIn) ? "page-card-excerpt page-excerpt-faded" : "page-card-excerpt";
                $html .= "<p class=\"{$excerptClass}\">{$page['formatted_excerpt']}</p>";
            }

            if ($parsedData['showFeatures'] && !empty($page['features'])) {
                $html .= "<div class=\"page-card-features\">";
                foreach ($page['features'] as $feature) {
                    $html .= "<span class=\"page-card-feature\">" . htmlspecialchars($feature) . "</span>";
                }
                $html .= "</div>";
            }

            if ($parsedData['showActions'] || ($isPrivate && !$isLoggedIn)) {
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
                    foreach ($page['actions'] as $action) {
                        $style = $action['style'] ?? 'primary';
                        $html .= "<a href=\"{$action['url']}\" class=\"page-card-action style-{$style}\">{$action['text']}</a>";
                    }
                }
                $html .= "</div>";
            }

            $html .= "</div>"; // page-card-content
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

    private function addLink(string $html, string $slug)
    {
        $original = $html;
        $url = $this->buildPageUrl($slug);

        if (!empty($url)) {
            $html = "<a href=\"{$url}\">{$original}</a>";
        }

        return $html;
    }

    private function generatePageCard(array $page, array $parsedData): string
    {
        $isProduct = !empty($page['price']);

        if ($isProduct) {
            return $this->generateProductCard($page, $parsedData);
        }

        // Check if page is private
        $isPrivate = $this->isPagePrivate($page['slug']);
        $isLoggedIn = MemberAuth::check();

        $html = "<div class=\"page-card" . ($isPrivate && !$isLoggedIn ? " page-card-private" : "") . "\">";

        // Image section
        if ($parsedData['showImage'] && !empty($page['image'])) {
            $html .= "<div class=\"page-image\">";

            $altText = htmlspecialchars($page['image']['alt'] ?: $page['title']);
            $titleText = htmlspecialchars($page['image']['title'] ?: $page['title']);

            // Add overlay for private content
            if ($isPrivate && !$isLoggedIn) {
                $html .= "<div class=\"private-overlay\"></div>";
                $html .= "<div class=\"private-badge\">🔒 Members Only</div>";
            }

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

        if (!$isPrivate || $isLoggedIn) {
            $html .= $this->generateToolbar();
        }

        // Content section
        $html .= "<div class=\"page-content" . ($isPrivate && !$isLoggedIn ? " page-content-faded" : "") . "\">";

        // Title
        $html .= "<h3 class=\"page-title\">";
        if ($isPrivate && !$isLoggedIn) {
            $html .= htmlspecialchars($page['title']);
        } else {
            $html .= "<a href=\"" . htmlspecialchars($page['url']) . "\">" . htmlspecialchars($page['title']) . "</a>";
        }
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
            $excerptClass = ($isPrivate && !$isLoggedIn) ? "page-excerpt page-excerpt-faded" : "page-excerpt";
            $html .= "<div class=\"{$excerptClass}\">" . htmlspecialchars($page['excerpt']) . "</div>";
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
        if (($parsedData['showActions'] && !empty($page['actions'])) || $isPrivate && !$isLoggedIn) {
            $html .= "<div class=\"page-actions\">";

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
                foreach ($page['actions'] as $action) {
                    $relAttr = !empty($action['rel']) ? " rel=\"{$action['rel']}\"" : '';
                    $targetAttr = $action['target'] !== '_self' ? " target=\"{$action['target']}\"" : '';

                    $site = SiteContext::get();
                    $url = str_replace('http://localhost:5001/shop/', '/' . $site->slug . '/shop/', $action['url']);

                    $html .= "<a href=\"" . htmlspecialchars($url) . "\" class=\"btn btn-{$action['style']}\"{$targetAttr}{$relAttr}>";
                    $html .= htmlspecialchars($action['text']);
                    $html .= "</a>";
                }
            }
            $html .= "</div>";
        }

        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }

    /**
     * Check if a page is private by fetching its metadata
     */
    private function isPagePrivate(string $slug): bool
    {
        try {
            $siteId = SiteContext::getId();

            $page = $this->pageRepository->findBySlug($slug, $siteId);

            if (!$page) {
                return false;
            }

            $metadata = $this->pageRepository->getMetaDataForPage($page->id);

            if (!$metadata) {
                return false;
            }

            return $metadata->visibility === 'private';
        } catch (\Exception $e) {
            Logger::error('Error checking page privacy: ' . $e->getMessage());
            return false;
        }
    }


    private function generateProductCard(array $page, array $parsedData): string
    {
        $productData = $page['product_data'] ?? null;
        $productId = $productData ? 'product-' . $productData['id'] : 'page-' . ($page['slug'] ?? uniqid());

        // Check if page is private
        $isPrivate = $this->isPagePrivate($page['slug']) || $page['is_private'] ?? false;
        $isLoggedIn = MemberAuth::check();
        $inWishlist = $isLoggedIn && Wishlist::where('product_id', $productData['id'])->where('site_id', SiteContext::getId())->exists();
        $wishlistClass = $inWishlist ? 'active' : '';

        // Use real product data if available
        $price = $productData ? $productData['price'] : ($page['price'] ?? '');
        $salePrice = $productData ? $productData['sale_price'] : null;
        $discountPercentage = $productData ? $productData['discount_percentage'] : 0;
        $category = $productData && $productData['category'] ? $productData['category'] : null;
        $brand = $productData && $productData['brand'] ? $productData['brand'] : null;
        $stockQuantity = $productData ? $productData['stock_quantity'] : null;

        $html = "<div class=\"page-card product-card" . ($isPrivate && !$isLoggedIn ? " page-card-private" : "") . "\" data-product-id=\"{$productId}\">";
        $html .= "<div class=\"product-card-inner\">";

        // FRONT OF CARD
        $html .= "<div class=\"product-card-front\">";

        // Only show flip button if user has access
        if (!$isPrivate || $isLoggedIn) {
            $html .= "<button class=\"btn-flip\" data-product-id=\"{$productId}\" title=\"View details\">";
            $html .= "<svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\">";
            $html .= "<path d=\"M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7\"/>";
            $html .= "</svg>";
            $html .= "</button>";
        }

        // Product image
        if ($parsedData['showImage'] && !empty($page['image'])) {
            $html .= "<div class=\"product-image\">";

            // Add overlay for private content
            if ($isPrivate && !$isLoggedIn) {
                $html .= "<div class=\"private-overlay\"></div>";
                $html .= "<div class=\"private-badge\">🔒 Members Only</div>";
            }

            if (!$isPrivate || $isLoggedIn) {
                $html .= "<a href=\"" . htmlspecialchars($page['url']) . "\">";
            }

            $html .= "<img src=\"" . htmlspecialchars($page['image']['src']) . "\" alt=\"" . htmlspecialchars($page['image']['alt'] ?: $page['title']) . "\">";

            if ($discountPercentage > 0 && (!$isPrivate || $isLoggedIn)) {
                $html .= "<span class=\"badge-sale\">-{$discountPercentage}%</span>";
            }

            if (!$isPrivate || $isLoggedIn) {
                $html .= "</a>";
            }

            $html .= "</div>";
        }

        if (!$isPrivate || $isLoggedIn) {
            $html .= $this->generateToolbar();
        }

        // Product content
        $html .= "<div class=\"product-content" . ($isPrivate && !$isLoggedIn ? " page-content-faded" : "") . "\">";

        // Category and Brand
        if (($category || $brand) && (!$isPrivate || $isLoggedIn)) {
            $html .= "<div class=\"product-meta-tags\">";
            if ($category) {
                $html .= "<span class=\"product-category-tag\">" . htmlspecialchars($category['name']) . "</span>";
            }
            if ($brand) {
                $html .= "<span class=\"product-brand-tag\">" . htmlspecialchars($brand['name']) . "</span>";
            }
            $html .= "</div>";
        }

        $html .= "<h3 class=\"product-name\">";
        if ($isPrivate && !$isLoggedIn) {
            $html .= htmlspecialchars($page['title']);
        } else {
            $html .= "<a href=\"" . htmlspecialchars($page['url']) . "\">" . htmlspecialchars($page['title']) . "</a>";
        }
        $html .= "</h3>";

        // Price - show blurred or partially hidden for private content
        if ($isPrivate && !$isLoggedIn) {
            $html .= "<div class=\"product-price\" style=\"filter: blur(4px); user-select: none;\">";
            $html .= "<span class=\"price-current\">XX.XX</span>";
            $html .= "</div>";
        } else {
            $html .= "<div class=\"product-price\">";
            if ($salePrice && $salePrice > 0 && $salePrice < $price) {
                $html .= "<span class=\"price-sale\">$" . number_format($salePrice, 2) . "</span>";
                $html .= "<span class=\"price-original\">$" . number_format($price, 2) . "</span>";
            } else {
                $html .= "<span class=\"price-current\">$" . number_format($price, 2) . "</span>";
            }
            $html .= "</div>";
        }

        // Stock indicator
        if ($stockQuantity !== null && (!$isPrivate || $isLoggedIn)) {
            $stockStatus = $this->getStockStatus($stockQuantity);
            $html .= "<div class=\"stock-indicator-small {$stockStatus['class']}\">";
            $html .= "<span class=\"stock-dot\"></span>";
            $html .= "<span>" . $stockStatus['text'] . "</span>";
            $html .= "</div>";
        }

        // Actions
        if ($parsedData['showActions'] || ($isPrivate && !$isLoggedIn)) {
            $html .= "<div class=\"product-actions\">";

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
                foreach ($page['actions'] as $action) {
                    $site = SiteContext::get();
                    $url = str_replace('http://localhost:5001/shop/', '/' . $site->slug . '/shop/', $action['url']);

                    $html .= "<a class=\"btn-wishlist {$wishlistClass}\" data-product-id=\"" . $productData['id'] . "\">";
                    $html .= '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-heart" width="20" height="20">
                                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                                </svg>';
                    $html .= "</a>";

                    $html .= "<a href=\"" . htmlspecialchars($url) . "\" class=\"btn-add-to-cart\">";
                    $html .= htmlspecialchars($action['text']);
                    $html .= "</a>";
                }
            }
            $html .= "</div>";
        }

        $html .= "</div>"; // product-content
        $html .= "</div>"; // product-card-front

        // BACK OF CARD - Only show if user has access
        if (!$isPrivate || $isLoggedIn) {
            $html .= $this->generateProductCardBack($page, $parsedData, $productData, $productId);
        }

        $html .= "</div>"; // product-card-inner
        $html .= "</div>"; // product-card

        return $html;
    }

    private function getStockStatus($quantity): array
    {
        if ($quantity === 0 || $quantity === null) {
            return ['class' => 'out-of-stock', 'text' => 'Out of Stock'];
        } elseif ($quantity < 10) {
            return ['class' => 'low-stock', 'text' => "Only {$quantity} left"];
        } else {
            return ['class' => 'in-stock', 'text' => 'In Stock'];
        }
    }

    private function generateProductCardBack(array $page, array $parsedData, ?array $productData, string $productId): string
    {
        $html = "<div class=\"product-card-back\">";
        $html .= "<div class=\"card-back-header\">";
        $html .= "<h3 class=\"card-back-title\">" . htmlspecialchars($page['title']) . "</h3>";
        $html .= "<button class=\"btn-flip-back\" data-product-id=\"{$productId}\" title=\"Flip back\">";
        $html .= "<svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\">";
        $html .= "<line x1=\"18\" y1=\"6\" x2=\"6\" y2=\"18\"></line>";
        $html .= "<line x1=\"6\" y1=\"6\" x2=\"18\" y2=\"18\"></line>";
        $html .= "</svg>";
        $html .= "</button>";
        $html .= "</div>";

        $html .= "<div class=\"card-back-content\">";

        if ($productData) {
            // Description
            if (!empty($productData['description'])) {
                $shortDesc = strlen($productData['description']) > 150
                    ? substr($productData['description'], 0, 150) . '...'
                    : $productData['description'];
                $html .= "<div class=\"back-section\">";
                $html .= "<h4 class=\"back-section-title\">Description</h4>";
                $html .= "<p class=\"product-description\">" . htmlspecialchars($shortDesc) . "</p>";
                $html .= "</div>";
            }

            // Stock Status
            $html .= "<div class=\"back-section\">";
            $html .= "<h4 class=\"back-section-title\">Availability</h4>";
            $stockStatus = $this->getStockStatus($productData['stock_quantity']);
            $html .= "<div class=\"stock-indicator {$stockStatus['class']}\">";
            $html .= "<span class=\"stock-dot\"></span>";
            $html .= "<span>{$stockStatus['text']}</span>";
            $html .= "</div>";
            $html .= "</div>";

            // Variants
            if (!empty($productData['variants']) && count($productData['variants']) > 0) {
                $html .= "<div class=\"back-section\">";
                $html .= "<h4 class=\"back-section-title\">Available Options</h4>";
                $html .= "<div class=\"variants-grid\">";
                foreach ($productData['variants'] as $variant) {
                    $disabledClass = !$variant['in_stock'] ? ' disabled' : '';
                    $html .= "<div class=\"variant-option{$disabledClass}\">";
                    $html .= "<div style=\"font-weight: 500;\">" . htmlspecialchars($variant['name']) . "</div>";
                    if ($variant['discount_percentage'] > 0) {
                        $html .= "<div style=\"font-size: 0.75rem; color: #059669;\">-{$variant['discount_percentage']}%</div>";
                    }
                    $html .= "<div style=\"font-size: 0.75rem; color: #64748b;\">$" . number_format($variant['final_price'], 2) . "</div>";
                    $html .= "</div>";
                }
                $html .= "</div>";
                $html .= "</div>";
            }

            // Price History
            if (!empty($productData['price_history']) && count($productData['price_history']) > 0) {
                $prices = array_column($productData['price_history'], 'price');
                $currentPrice = end($prices);
                $lowestPrice = min($prices);
                $highestPrice = max($prices);
                $savingsPercent = 0;

                if ($currentPrice == $lowestPrice && $highestPrice > $lowestPrice) {
                    $savingsPercent = round((($highestPrice - $lowestPrice) / $highestPrice) * 100);
                }

                $html .= "<div class=\"back-section\">";
                $html .= "<h4 class=\"back-section-title\">Price History (90 Days)</h4>";
                $html .= "<div class=\"price-chart-container\">";
                $html .= "<div class=\"price-stats\">";
                $html .= "<div class=\"price-stat\">";
                $html .= "<div class=\"price-stat-label\">Current</div>";
                $html .= "<div class=\"price-stat-value current\">$" . number_format($currentPrice, 2) . "</div>";
                $html .= "</div>";
                $html .= "<div class=\"price-stat\">";
                $html .= "<div class=\"price-stat-label\">Lowest</div>";
                $html .= "<div class=\"price-stat-value low\">$" . number_format($lowestPrice, 2) . "</div>";
                $html .= "</div>";
                $html .= "<div class=\"price-stat\">";
                $html .= "<div class=\"price-stat-label\">Highest</div>";
                $html .= "<div class=\"price-stat-value high\">$" . number_format($highestPrice, 2) . "</div>";
                $html .= "</div>";
                $html .= "</div>";

                if ($savingsPercent > 0) {
                    $html .= "<div style=\"text-align: center; margin-bottom: 0.5rem; color: #059669; font-size: 0.875rem; font-weight: 500;\">";
                    $html .= "💰 Save {$savingsPercent}% vs highest price!";
                    $html .= "</div>";
                }

                $html .= "<div class=\"price-chart\">";
                $html .= "<svg class=\"price-chart-line\" viewBox=\"0 0 100 40\" preserveAspectRatio=\"none\">";
                $html .= $this->generatePriceChartSVG($productData['price_history']);
                $html .= "</svg>";
                $html .= "</div>";
                $html .= "</div>";
                $html .= "</div>";
            }

            // Specifications
            if (!empty($productData['specifications']) && count($productData['specifications']) > 0) {
                $html .= "<div class=\"back-section\">";
                $html .= "<h4 class=\"back-section-title\">Specifications</h4>";
                $html .= "<div class=\"comparison-section\">";
                foreach ($productData['specifications'] as $spec) {
                    $html .= "<div class=\"comparison-item\">";
                    $html .= "<span class=\"comparison-label\">" . htmlspecialchars($spec['key']) . "</span>";
                    $html .= "<span class=\"comparison-value\">" . htmlspecialchars($spec['value']) . "</span>";
                    $html .= "</div>";
                }
                $html .= "</div>";
                $html .= "</div>";
            }

            // Comparison
            if (!empty($productData['comparison'])) {
                $comp = $productData['comparison'];
                $html .= "<div class=\"back-section\">";
                $html .= "<h4 class=\"back-section-title\">Price Comparison</h4>";
                $html .= "<div class=\"comparison-section\">";
                $html .= "<div class=\"comparison-item\">";
                $html .= "<span class=\"comparison-label\">vs. Category Average</span>";
                $html .= "<span class=\"comparison-badge {$comp['price_comparison']}\">{$comp['price_difference']}</span>";
                $html .= "</div>";

                if (!empty($comp['category_avg_price'])) {
                    $html .= "<div class=\"comparison-item\">";
                    $html .= "<span class=\"comparison-label\">Category Average</span>";
                    $html .= "<span class=\"comparison-value\">\$" . $comp['category_avg_price'] . "</span>";
                    $html .= "</div>";
                }

                if (!empty($comp['discount_vs_regular'])) {
                    $html .= "<div class=\"comparison-item\">";
                    $html .= "<span class=\"comparison-label\">Your Savings</span>";
                    $html .= "<span class=\"comparison-badge better\">{$comp['discount_vs_regular']}</span>";
                    $html .= "</div>";
                }

                if (!empty($comp['products_in_category'])) {
                    $html .= "<div class=\"comparison-item\">";
                    $html .= "<span class=\"comparison-label\">Similar Products</span>";
                    $html .= "<span class=\"comparison-value\">{$comp['products_in_category']} in category</span>";
                    $html .= "</div>";
                }

                $html .= "</div>";
                $html .= "</div>";
            }

            // Merchant availability
            if (!empty($productData['merchants']) && count($productData['merchants']) > 1) {
                $html .= "<div class=\"back-section\">";
                $html .= "<h4 class=\"back-section-title\">Available From</h4>";
                $html .= "<div class=\"comparison-section\">";
                foreach (array_slice($productData['merchants'], 0, 3) as $merchant) {
                    $merchantPrice = $merchant['sale_price'] > 0 ? $merchant['sale_price'] : $merchant['price'];
                    $html .= "<div class=\"comparison-item\">";
                    $html .= "<span class=\"comparison-label\">";
                    $html .= "<a href=\"" . htmlspecialchars($merchant['url']) . "\" target=\"_blank\" style=\"color: #2563eb; text-decoration: none;\">";
                    $html .= htmlspecialchars($merchant['name']);
                    $html .= "</a>";
                    $html .= "</span>";
                    $html .= "<span class=\"comparison-value\">";
                    $html .= "$" . number_format($merchantPrice, 2);
                    if ($merchant['has_discount']) {
                        $html .= "<span style=\"color: #059669; font-size: 0.75rem; margin-left: 0.25rem;\">";
                        $html .= "-{$merchant['discount_percentage']}%";
                        $html .= "</span>";
                    }
                    $html .= "</span>";
                    $html .= "</div>";
                }
                if (count($productData['merchants']) > 3) {
                    $remaining = count($productData['merchants']) - 3;
                    $html .= "<div style=\"text-align: center; margin-top: 0.5rem; font-size: 0.875rem; color: #64748b;\">";
                    $html .= "+{$remaining} more retailers";
                    $html .= "</div>";
                }
                $html .= "</div>";
                $html .= "</div>";
            }

        } else {
            // Fallback to basic page data if no product data
            if (!empty($page['excerpt'])) {
                $html .= "<div class=\"back-section\">";
                $html .= "<h4 class=\"back-section-title\">Description</h4>";
                $html .= "<p class=\"product-description\">" . htmlspecialchars($page['excerpt']) . "</p>";
                $html .= "</div>";
            }

            if ($parsedData['showFeatures'] && !empty($page['features'])) {
                $html .= "<div class=\"back-section\">";
                $html .= "<h4 class=\"back-section-title\">Features</h4>";
                $html .= "<div class=\"comparison-section\">";
                foreach ($page['features'] as $feature) {
                    $html .= "<div class=\"comparison-item\">";
                    $html .= "<span class=\"comparison-value\">✓ " . htmlspecialchars($feature) . "</span>";
                    $html .= "</div>";
                }
                $html .= "</div>";
                $html .= "</div>";
            }
        }

        $html .= "</div>"; // card-back-content

        // Back actions
        if ($parsedData['showActions'] && !empty($page['actions'])) {
            $html .= "<div class=\"card-back-actions\">";
            foreach ($page['actions'] as $action) {
                $site = SiteContext::get();
                $url = str_replace('http://localhost:5001/shop/', '/' . $site->slug . '/shop/', $action['url']);
                $html .= "<a href=\"" . htmlspecialchars($url) . "\" class=\"btn-back-action btn-view-details\">";
                $html .= htmlspecialchars($action['text']);
                $html .= "</a>";
            }
            $html .= "</div>";
        }

        $html .= "</div>"; // product-card-back

        return $html;
    }

    private function generatePriceChartSVG(array $priceHistory): string
    {
        if (empty($priceHistory) || count($priceHistory) < 2) {
            return '';
        }

        $prices = array_column($priceHistory, 'price');
        $minPrice = min($prices);
        $maxPrice = max($prices);
        $priceRange = $maxPrice - $minPrice ?: 1;

        $points = [];
        foreach ($priceHistory as $index => $item) {
            $x = ($index / (count($priceHistory) - 1)) * 100;
            $y = 40 - (($item['price'] - $minPrice) / $priceRange) * 35;
            $points[] = "{$x},{$y}";
        }

        $pointsStr = implode(' ', $points);

        return "<polyline points=\"{$pointsStr}\" fill=\"none\" stroke=\"#2563eb\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\" />";
    }
}