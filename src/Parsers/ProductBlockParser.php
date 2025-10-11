<?php

namespace App\Parsers;

use App\Enums\Currency;
use App\Enums\DisplayAs;
use App\Enums\Layout;
use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\EnumRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\MinLengthRule;
use App\Framework\Validation\Rules\MinRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Framework\Validation\Rules\UrlRule;
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
            'formatted_description' => nl2br(htmlspecialchars($description))
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
        $html = "<div class=\"product-block product-layout-{$parsedData['layout']}\">";

        if (!empty($parsedData['image'])) {
            $html .= "<div class=\"product-image\">";
            $html .= "<img src=\"{$parsedData['image']['src']}\" alt=\"{$parsedData['name']}\" class=\"product-img\">";
            $html .= "</div>";
        }

        $html .= "<div class=\"product-details\">";

        if (!empty($parsedData['brand'])) {
            $html .= "<div class=\"product-brand\">{$parsedData['brand']}</div>";
        }

        $html .= "<h3 class=\"product-name\">{$parsedData['name']}</h3>";

        $html .= "<div class=\"product-pricing\">";

        if ($parsedData['has_sale_price']) {
            $html .= "<span class=\"product-price original\">{$parsedData['currency']}{$parsedData['price']}</span>";
            $html .= "<span class=\"product-price sale\">{$parsedData['currency']}{$parsedData['salePrice']}</span>";
            $html .= "<span class=\"product-savings\">Save {$parsedData['currency']}" . ($parsedData['price'] - $parsedData['salePrice']) . "</span>";
        } else {
            $html .= "<span class=\"product-price\">{$parsedData['currency']}{$parsedData['price']}</span>";
        }

        $html .= "</div>";

        if (!empty($parsedData['description'])) {
            $html .= "<div class=\"product-description\">{$parsedData['formatted_description']}</div>";
        }

        // Review panel
        if ($parsedData['showReviewPanel'] && !empty($parsedData['review'])) {
            $review = $parsedData['review'];
            $html .= "<div class=\"product-review-panel\">";

            if (!empty($review['rating'])) {
                $html .= "<div class=\"review-rating\">Rating: {$review['rating']}/5</div>";
            }

            if (!empty($review['pros'])) {
                $html .= "<div class=\"review-pros\">";
                $html .= "<h4>Pros:</h4>";
                $html .= "<ul>";
                foreach ($review['pros'] as $pro) {
                    $html .= "<li>{$pro}</li>";
                }
                $html .= "</ul>";
                $html .= "</div>";
            }

            if (!empty($review['cons'])) {
                $html .= "<div class=\"review-cons\">";
                $html .= "<h4>Cons:</h4>";
                $html .= "<ul>";
                foreach ($review['cons'] as $con) {
                    $html .= "<li>{$con}</li>";
                }
                $html .= "</ul>";
                $html .= "</div>";
            }

            $html .= "</div>";
        }

        // Product link/button
        $linkAttrs = '';
        if ($parsedData['noFollow']) $linkAttrs .= ' rel="nofollow"';
        if ($parsedData['sponsored']) $linkAttrs .= ' rel="sponsored"';
        if ($parsedData['openInNewTab']) $linkAttrs .= ' target="_blank"';

        $html .= "<a href=\"{$parsedData['link']}\" class=\"product-link product-display-{$parsedData['displayAs']}\"{$linkAttrs}>";
        $html .= "{$parsedData['linkText']}";
        $html .= "</a>";

        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }
}