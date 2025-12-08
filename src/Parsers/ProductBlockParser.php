<?php

namespace App\Parsers;

use App\Enums\Currency;
use App\Enums\DisplayAs;
use App\Enums\Layout;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Support\SiteContext;
use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\EnumRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\MinLengthRule;
use App\Framework\Validation\Rules\MinRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Framework\Validation\Rules\UrlRule;
use App\Models\Wishlist;
use App\Validation\Custom\SalePriceValidatorRule;

class ProductBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'product';
    }

    public function getValidationRules(): array
    {
        return [
            'link' => [
                new RequiredRule(),
                new UrlRule()
            ],
            'noFollow' => [
                new BooleanRule()
            ],
            'sponsored' => [
                new BooleanRule()
            ],
            'openInNewTab' => [
                new BooleanRule()
            ],
            'displayAs' => [
                new EnumRule(DisplayAs::class)
            ],
            'linkText' => [
                new MaxLengthRule(100)
            ],
            'image' => [
                new ArrayRule()
            ],
            'name' => [
                new RequiredRule(),
                new MinLengthRule(2),
                new MaxLengthRule(255)
            ],
            'brand' => [
                new MaxLengthRule(255)
            ],
            'productName' => [
                new RequiredRule(),
                new MinLengthRule(2),
                new MaxLengthRule(255)
            ],
            'currency' => [
                new EnumRule(Currency::class)
            ],
            'price' => [
                new RequiredRule(),
                new MinRule(0.01)
            ],
            'salePrice' => [
                new MinRule(0),
                new SalePriceValidatorRule()
            ],
            'layout' => [
                new EnumRule(Layout::class)
            ],
            'description' => [
                new MaxLengthRule(1000)
            ]
        ];
    }

    public function parse(array $data): array
    {
        $price = (float)($data['price'] ?? 0);
        $salePrice = (float)($data['salePrice'] ?? 0);
        $description = trim($data['description'] ?? '');

        return [
            'link' => $data['link'] ?? '',
            'noFollow' => (bool)($data['noFollow'] ?? false),
            'sponsored' => (bool)($data['sponsored'] ?? false),
            'openInNewTab' => (bool)($data['openInNewTab'] ?? false),
            'displayAs' => $data['displayAs'] ?? 'button',
            'linkText' => $data['linkText'] ?? 'Buy Now',
            'image' => $data['image'] ?? null,
            'name' => trim($data['name'] ?? ''),
            'brand' => trim($data['brand'] ?? ''),
            'productName' => trim($data['productName'] ?? ''),
            'currency' => $data['currency'] ?? '$',
            'price' => $price,
            'salePrice' => $salePrice,
            'layout' => $data['layout'] ?? 'standard',
            'description' => $description,
            'showReviewPanel' => (bool)($data['showReviewPanel'] ?? false),
            'review' => $this->parseReviewData($data['review'] ?? []),
            'has_sale_price' => $salePrice > 0 && $salePrice < $price,
            'description_word_count' => str_word_count(strip_tags($description)),
            'formatted_description' => nl2br(htmlspecialchars($description)),
            'product_id' => $data['product_id'] ?? null,
            'variant_id' => $data['variant_id'] ?? null,
            'opted_out_product_match' => (bool)($data['opted_out_product_match'] ?? false),
        ];
    }

    private function parseReviewData(array $review): ?array
    {
        if (empty($review)) {
            return null;
        }

        return [
            'pros' => array_filter(array_map('trim', $review['pros'] ?? [])),
            'cons' => array_filter(array_map('trim', $review['cons'] ?? [])),
            'rating' => (float)($review['rating'] ?? 0),
            'reviewPercent' => (int)($review['reviewPercent'] ?? 0),
            'articleUrl' => $review['articleUrl'] ?? '',
            'articleId' => $review['articleId'] ?? '',
            'articleTitle' => trim($review['articleTitle'] ?? '')
        ];
    }

    public function generateHtml(array $parsedData): string
    {
        //$html = "<div class=\"product-block-card product-layout-{$parsedData['layout']}\">";

        $productId = 'product-' . ($parsedData['product_id'] ?? uniqid());
        $price = $parsedData['price'];
        $salePrice = $parsedData['salePrice'];
        $hasSalePrice = $parsedData['has_sale_price'];
        $discountPercentage = 0;
        $isLoggedIn = MemberAuth::check();
        $inWishlist = $isLoggedIn && Wishlist::where('product_id', $parsedData['product_id'])->where('site_id', SiteContext::getId())->exists();
        $wishlistClass = $inWishlist ? 'active' : '';
        $brand = $parsedData && $parsedData['brand'] ? $parsedData['brand'] : null;

        if ($hasSalePrice) {
            $discountPercentage = round((($price - $salePrice) / $price) * 100);
        }

        $html = "<div class=\"product-card\" data-product-id=\"{$productId}\">";
        $html .= "<div class=\"product-card-inner\">";

        // FRONT OF CARD
        $html .= "<div class=\"product-card-front\">";

        // Card Actions Container (Flip + Wishlist)
        $html .= "<div class=\"product-card-actions-top\">";

        // Flip button
        $html .= "<button class=\"btn-flip\" data-product-id=\"{$productId}\" title=\"View details\">";
        $html .= "<svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\">";
        $html .= "<path d=\"M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7\"/>";
        $html .= "</svg>";
        $html .= "</button>";

        // Wishlist button
        $html .= "<button class=\"btn-wishlist-card btn-wishlist {$wishlistClass}\" data-product-id=\"{$productId}\" title=\"Add to wishlist\">";
        $html .= "<svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\">";
        $html .= "<path d=\"M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z\"/>";
        $html .= "</svg>";
        $html .= "</button>";

        $html .= "</div>"; // product-card-actions-top

        // Product image
        if (!empty($parsedData['image']) && !empty($parsedData['image']['src'])) {
            $html .= "<div class=\"product-image\">";
            $html .= "<a href=\"" . htmlspecialchars($parsedData['link']) . "\">";
            $html .= "<img src=\"" . htmlspecialchars($parsedData['image']['src']) . "\" alt=\"" . htmlspecialchars($parsedData['name']) . "\">";

            if ($discountPercentage > 0) {
                $html .= "<span class=\"badge-sale\">-{$discountPercentage}%</span>";
            }

            if ($parsedData['sponsored']) {
                $html .= "<span class=\"product-badge sponsored\">Sponsored</span>";
            }

            $html .= "</a>";
            $html .= "</div>";
        }

        // Product content
        $html .= "<div class=\"product-content\">";

        // Brand
        if (!empty($parsedData['brand'])) {
            $html .= "<div class=\"product-meta-tags\">";
            $html .= "<span class=\"product-brand-tag\">" . htmlspecialchars($parsedData['brand']) . "</span>";
            $html .= "</div>";
        }

        $html .= "<h3 class=\"product-name\">";
        $html .= "<a href=\"" . htmlspecialchars($parsedData['link']) . "\">" . htmlspecialchars($parsedData['name']) . "</a>";
        $html .= "</h3>";

        // Price
        $html .= "<div class=\"product-price\">";
        if ($hasSalePrice) {
            $html .= "<span class=\"price-sale\">{$parsedData['currency']}" . number_format($salePrice, 2) . "</span>";
            $html .= "<span class=\"price-original\">{$parsedData['currency']}" . number_format($price, 2) . "</span>";
        } else {
            $html .= "<span class=\"price-current\">{$parsedData['currency']}" . number_format($price, 2) . "</span>";
        }
        $html .= "</div>";

        // Actions
        $linkAttrs = '';
        if ($parsedData['noFollow']) $linkAttrs .= ' rel="nofollow"';
        if ($parsedData['sponsored']) $linkAttrs .= ' rel="sponsored"';
        if ($parsedData['openInNewTab']) $linkAttrs .= ' target="_blank"';

        $html .= "<div class=\"product-actions\">";
        $html .= "<a href=\"{$parsedData['link']}\" class=\"btn-add-to-cart\"{$linkAttrs}>";
        $html .= htmlspecialchars($parsedData['linkText']);
        $html .= "</a>";
        $html .= "</div>";

        $html .= "</div>"; // product-content
        $html .= "</div>"; // product-card-front

        // BACK OF CARD
        $html .= $this->generateProductCardBack($parsedData, $productId);

        $html .= "</div>"; // product-card-inner
        $html .= "</div>"; // product-card
        //$html .= "</div>";

        return $html;
    }

    private function generateProductCardBack(array $parsedData, string $productId): string
    {
        $html = "<div class=\"product-card-back\">";
        $html .= "<div class=\"card-back-header\">";
        $html .= "<h3 class=\"card-back-title\">" . htmlspecialchars($parsedData['name']) . "</h3>";
        $html .= "<button class=\"btn-flip-back\" data-product-id=\"{$productId}\" title=\"Flip back\">";
        $html .= "<svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\">";
        $html .= "<line x1=\"18\" y1=\"6\" x2=\"6\" y2=\"18\"></line>";
        $html .= "<line x1=\"6\" y1=\"6\" x2=\"18\" y2=\"18\"></line>";
        $html .= "</svg>";
        $html .= "</button>";
        $html .= "</div>";

        $html .= "<div class=\"card-back-content\">";

        // Description
        if (!empty($parsedData['description'])) {
            $html .= "<div class=\"back-section\">";
            $html .= "<h4 class=\"back-section-title\">Description</h4>";
            $html .= "<p class=\"product-description\">" . htmlspecialchars($parsedData['description']) . "</p>";
            $html .= "</div>";
        }

        // Review panel
        if ($parsedData['showReviewPanel'] && !empty($parsedData['review'])) {
            $review = $parsedData['review'];
            $html .= "<div class=\"back-section\">";
            $html .= "<h4 class=\"back-section-title\">Review</h4>";

            if (!empty($review['rating'])) {
                $stars = str_repeat('⭐', (int)$review['rating']);
                $html .= "<div class=\"review-rating\">{$stars} {$review['rating']}/5</div>";
            }

            if (!empty($review['pros'])) {
                $html .= "<div class=\"review-pros\"><strong>Pros:</strong><ul>";
                foreach ($review['pros'] as $pro) {
                    $html .= "<li>" . htmlspecialchars($pro) . "</li>";
                }
                $html .= "</ul></div>";
            }

            if (!empty($review['cons'])) {
                $html .= "<div class=\"review-cons\"><strong>Cons:</strong><ul>";
                foreach ($review['cons'] as $con) {
                    $html .= "<li>" . htmlspecialchars($con) . "</li>";
                }
                $html .= "</ul></div>";
            }

            $html .= "</div>";
        }

        $html .= "</div>"; // card-back-content

        // Back actions
        $linkAttrs = '';
        if ($parsedData['noFollow']) $linkAttrs .= ' rel="nofollow"';
        if ($parsedData['sponsored']) $linkAttrs .= ' rel="sponsored"';
        if ($parsedData['openInNewTab']) $linkAttrs .= ' target="_blank"';

        $html .= "<div class=\"card-back-actions\">";
        $html .= "<a href=\"{$parsedData['link']}\" class=\"btn-back-action btn-view-details\"{$linkAttrs}>";
        $html .= htmlspecialchars($parsedData['linkText']);
        $html .= "</a>";
        $html .= "</div>";

        $html .= "</div>"; // product-card-back

        return $html;
    }
}