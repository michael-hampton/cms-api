<?php

namespace App\Parsers;

use App\Enums\Currency;
use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\EnumRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\MinLengthRule;
use App\Framework\Validation\Rules\MinRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Framework\Validation\Rules\UrlRule;
use App\Validation\Custom\SalePriceValidatorRule;

class DealBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'deal';
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
            'title' => [
                new RequiredRule(),
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
            'image' => [
                new ArrayRule()
            ],
            'currency' => [
                new RequiredRule(),
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
           'savingMode' => [
               new MaxLengthRule(20)
            ],
            'description' => [
                new MaxLengthRule(1000)
            ],
            'showDealButton' => [
               new BooleanRule()
            ],
            'starBlock' => [
                new BooleanRule()
            ],
            'voucherId' => [
                new MaxLengthRule(255)
            ],
            'context' => [
                new MaxLengthRule(20)
            ]
        ];
    }

    public function parse(array $data): array
    {
        $price = (float)($data['price'] ?? 0);
        $salePrice = (float)($data['salePrice'] ?? 0);
        $savings = $price > $salePrice ? $price - $salePrice : 0;
        $savingsPercent = $price > 0 ? round(($savings / $price) * 100) : 0;

        return [
            'title' => trim($data['title'] ?? ''),
            'productName' => trim($data['productName'] ?? ''),
            'brand' => trim($data['brand'] ?? ''),
            'description' => trim($data['description'] ?? ''),
            'price' => $price,
            'salePrice' => $salePrice,
            'currency' => $data['currency'] ?? '£',
            'link' => $data['link'] ?? '',
            'image' => $data['image'] ?? null,
            'noFollow' => (bool)($data['noFollow'] ?? false),
            'sponsored' => (bool)($data['sponsored'] ?? false),
            'openInNewTab' => (bool)($data['openInNewTab'] ?? false),
            'showDealButton' => (bool)($data['showDealButton'] ?? true),
            'starBlock' => (bool)($data['starBlock'] ?? false),
            'savings' => $savings,
            'savings_percent' => $savingsPercent,
            'savingMode' => $data['savingMode'] ?? 'percent',
            'has_savings' => $savings > 0,
            'voucherId' => $data['voucherId'] ?? '',
            'has_voucher' => !empty($data['voucherId']),
            'formatted_description' => nl2br(htmlspecialchars($data['description'] ?? '')),
            'link_attributes' => $this->buildLinkAttributes(
                $data['noFollow'] ?? false,
                $data['sponsored'] ?? false,
                $data['openInNewTab'] ?? false
            ),
            'context' => $data['context'] ?? 'default',
            'product_id' => $data['product_id'] ?? null,
            'variant_id' => $data['variant_id'] ?? null,
            'opted_out_product_match' => (bool)($data['opted_out_product_match'] ?? false),
        ];
    }

    private function calculateSavingAmount(float $price, float $salePrice): float
    {
        return max(0, $price - $salePrice);
    }

    private function calculateSavingPercent(float $price, float $salePrice): int
    {
        if ($price <= 0) {
            return 0;
        }

        $savingAmount = $this->calculateSavingAmount($price, $salePrice);
        return (int)round(($savingAmount / $price) * 100);
    }

    private function buildLinkAttributes(bool $noFollow, bool $sponsored, bool $openInNewTab): array
    {
        $attributes = [];

        if ($openInNewTab) {
            $attributes['target'] = '_blank';
            $attributes['rel'] = 'noopener noreferrer';
        }

        $relValues = [];
        if ($noFollow) $relValues[] = 'nofollow';
        if ($sponsored) $relValues[] = 'sponsored';

        if (!empty($relValues)) {
            if (isset($attributes['rel'])) {
                $attributes['rel'] .= ' ' . implode(' ', $relValues);
            } else {
                $attributes['rel'] = implode(' ', $relValues);
            }
        }

        return $attributes;
    }

    public function generateHtml(array $parsedData): string
    {
        $contextClass = $parsedData['context'] === 'sidebar' ? ' deal-sidebar' : '';
        $html = "<div class=\"deal-block{$contextClass}\">";

        if ($parsedData['sponsored']) {
            $html .= "<span class=\"sponsored-badge\">Sponsored</span>";
        }

        // Add voucher badge if present
        if ($parsedData['has_voucher']) {
            $html .= "<span class=\"voucher-badge\">🎟️ Voucher Available</span>";
        }

        if (!empty($parsedData['image']) && !empty($parsedData['image']['src'])) {
            $html .= "<div class=\"deal-image\">";
            $html .= "<img src=\"{$parsedData['image']['src']}\" alt=\"{$parsedData['productName']}\" class=\"deal-img\">";
            $html .= "</div>";
        }

        $html .= "<div class=\"deal-content\">";
        $html .= "<h3 class=\"deal-title\">{$parsedData['title']}</h3>";

        if (!empty($parsedData['brand'])) {
            $html .= "<div class=\"deal-brand\">{$parsedData['brand']}</div>";
        }

        $html .= "<h4 class=\"deal-product\">{$parsedData['productName']}</h4>";

        if (!empty($parsedData['description'])) {
            $html .= "<div class=\"deal-description\">{$parsedData['formatted_description']}</div>";
        }

        $html .= "<div class=\"deal-pricing\">";
        if ($parsedData['has_savings']) {
            $html .= "<span class=\"deal-original-price\">{$parsedData['currency']}{$parsedData['price']}</span>";
            $html .= "<span class=\"deal-sale-price\">{$parsedData['currency']}{$parsedData['salePrice']}</span>";
            $html .= "<span class=\"deal-savings\">Save {$parsedData['currency']}{$parsedData['savings']} ({$parsedData['savings_percent']}%)</span>";
        } else {
            $html .= "<span class=\"deal-price\">{$parsedData['currency']}{$parsedData['price']}</span>";
        }
        $html .= "</div>";

        // Add voucher section before the button
        if ($parsedData['has_voucher']) {
            $html .= "<div class=\"deal-voucher\">";
            $html .= "<span class=\"voucher-label\">Use Code:</span>";
            $html .= "<span class=\"voucher-code\">{$parsedData['voucherId']}</span>";
            $html .= "<button class=\"voucher-copy-btn\" onclick=\"navigator.clipboard.writeText('{$parsedData['voucherId']}')\">Copy</button>";
            $html .= "</div>";
        }

        if ($parsedData['showDealButton'] && !empty($parsedData['link'])) {
            $linkAttrs = '';
            foreach ($parsedData['link_attributes'] as $attr => $value) {
                $linkAttrs .= " {$attr}=\"{$value}\"";
            }
            $html .= "<a href=\"{$parsedData['link']}\"{$linkAttrs} class=\"deal-button\">Get Deal</a>";
        }

        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }
}