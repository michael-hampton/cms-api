<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\InRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;

class BannerBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'banner';
    }

    public function getValidationRules(): array
    {
        return [
            'bannerType' => [
                new RequiredRule(),
                new InRule(['promo-header', 'review-banner', 'providers-banner'])
            ],
            'title' => [new RequiredRule(), new MaxLengthRule(255)],
            'subtitle' => [new MaxLengthRule(500)],
            'ctaText' => [new MaxLengthRule(100)],
            'backgroundColor' => [new MaxLengthRule(50)],
            'textColor' => [new MaxLengthRule(50)],
            'image' => [new ArrayRule()],
            'providers' => [new ArrayRule()],
            'rating' => [],
            'reviewCount' => [],
            'showDismiss' => [new BooleanRule()],
            'dismissible' => [new BooleanRule()]
        ];
    }

    public function parse(array $data): array
    {
        $bannerType = $data['bannerType'] ?? 'promo-header';

        return [
            'bannerType' => $bannerType,
            'title' => trim($data['title'] ?? ''),
            'subtitle' => trim($data['subtitle'] ?? ''),
            'ctaText' => trim($data['ctaText'] ?? ''),
            'ctaUrl' => $data['ctaUrl'] ?? '',
            'backgroundColor' => $data['backgroundColor'] ?? '#007bff',
            'textColor' => $data['textColor'] ?? '#ffffff',
            'image' => $data['image'] ?? null,
            'providers' => $data['providers'] ?? [],
            'rating' => (float)($data['rating'] ?? 0),
            'reviewCount' => (int)($data['reviewCount'] ?? 0),
            'showDismiss' => (bool)($data['showDismiss'] ?? false),
            'dismissible' => (bool)($data['dismissible'] ?? false),
            'context' => $data['context'] ?? 'default'
        ];
    }

    public function generateHtml(array $parsedData): string
    {
        $context = $parsedData['context'] ?? 'default';

        if ($context === 'sidebar') {
            return $this->generateSidebarBannerHtml($parsedData);
        }

        return $this->generateDefaultBannerHtml($parsedData);
    }

    private function generateSidebarBannerHtml(array $parsedData): string
    {
        $bgColor = $parsedData['backgroundColor'];
        $textColor = $parsedData['textColor'];

        $html = "<div class=\"banner banner-sidebar\" style=\"background-color: {$bgColor}; color: {$textColor};\">";
        $html .= "<div class=\"banner-content-sidebar\">";

        if (!empty($parsedData['image'])) {
            $html .= "<img src=\"{$parsedData['image']['src']}\" alt=\"{$parsedData['title']}\" class=\"banner-image-sidebar\">";
        }

        $html .= "<h4 class=\"banner-title-sidebar\">{$parsedData['title']}</h4>";

        if (!empty($parsedData['subtitle'])) {
            $html .= "<p class=\"banner-subtitle-sidebar\">{$parsedData['subtitle']}</p>";
        }

        if (!empty($parsedData['ctaText']) && !empty($parsedData['ctaUrl'])) {
            $html .= "<a href=\"{$parsedData['ctaUrl']}\" class=\"banner-cta-sidebar btn btn-sm btn-primary\">{$parsedData['ctaText']}</a>";
        }

        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }

    private function generateDefaultBannerHtml(array $parsedData): string
    {
        $bannerType = $parsedData['bannerType'];

        switch ($bannerType) {
            case 'promo-header':
                return $this->generatePromoHeaderHtml($parsedData);
            case 'review-banner':
                return $this->generateReviewBannerHtml($parsedData);
            case 'providers-banner':
                return $this->generateProvidersBannerHtml($parsedData);
            default:
                return '';
        }
    }

    private function generatePromoHeaderHtml(array $parsedData): string
    {
        $bgColor = $parsedData['backgroundColor'];
        $textColor = $parsedData['textColor'];
        $dismissClass = $parsedData['dismissible'] ? 'dismissible' : '';

        $html = "<div class=\"banner banner-promo-header {$dismissClass}\" style=\"background-color: {$bgColor}; color: {$textColor};\">";

        if ($parsedData['dismissible']) {
            $html .= "<button class=\"banner-dismiss\" onclick=\"this.parentElement.remove()\">&times;</button>";
        }

        $html .= "<div class=\"banner-content\">";
        $html .= "<h2 class=\"banner-title\">{$parsedData['title']}</h2>";

        if (!empty($parsedData['subtitle'])) {
            $html .= "<p class=\"banner-subtitle\">{$parsedData['subtitle']}</p>";
        }

        if (!empty($parsedData['ctaText']) && !empty($parsedData['ctaUrl'])) {
            $html .= "<a href=\"{$parsedData['ctaUrl']}\" class=\"banner-cta btn btn-primary\">{$parsedData['ctaText']}</a>";
        }

        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }

    private function generateReviewBannerHtml(array $parsedData): string
    {
        $bgColor = $parsedData['backgroundColor'];
        $textColor = $parsedData['textColor'];
        $rating = $parsedData['rating'];
        $reviewCount = $parsedData['reviewCount'];

        die('here');

        $html = "<div class=\"banner banner-review\" style=\"background-color: {$bgColor}; color: {$textColor};\">";
        $html .= "<div class=\"banner-content\">";

        if (!empty($parsedData['image'])) {
            $html .= "<img src=\"{$parsedData['image']['src']}\" alt=\"{$parsedData['title']}\" class=\"banner-image\">";
        }

        $html .= "<div class=\"banner-text\">";
        $html .= "<h3 class=\"banner-title\">{$parsedData['title']}</h3>";

        if ($rating > 0) {
            $stars = $this->generateStars($rating);
            $html .= "<div class=\"banner-rating\">";
            $html .= "<span class=\"stars\">{$stars}</span>";
            $html .= "<span class=\"rating-value\">{$rating}/5</span>";

            if ($reviewCount > 0) {
                $html .= "<span class=\"review-count\">({$reviewCount} reviews)</span>";
            }

            $html .= "</div>";
        }

        if (!empty($parsedData['ctaText']) && !empty($parsedData['ctaUrl'])) {
            $html .= "<a href=\"{$parsedData['ctaUrl']}\" class=\"banner-cta btn btn-primary\">{$parsedData['ctaText']}</a>";
        }

        $html .= "</div>";
        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }

    private function generateStars(float $rating): string
    {
        $fullStars = floor($rating);
        $halfStar = ($rating - $fullStars) >= 0.5;
        $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);

        $html = '';

        for ($i = 0; $i < $fullStars; $i++) {
            $html .= '★';
        }

        if ($halfStar) {
            $html .= '☆';
        }

        for ($i = 0; $i < $emptyStars; $i++) {
            $html .= '☆';
        }

        return $html;
    }

    private function generateProvidersBannerHtml(array $parsedData): string
    {
        $bgColor = $parsedData['backgroundColor'];
        $textColor = $parsedData['textColor'];
        $providers = $parsedData['providers'];

        $html = "<div class=\"banner banner-providers\" style=\"background-color: {$bgColor}; color: {$textColor};\">";
        $html .= "<div class=\"banner-content\">";
        $html .= "<h3 class=\"banner-title\">{$parsedData['title']}</h3>";

        if (!empty($parsedData['subtitle'])) {
            $html .= "<p class=\"banner-subtitle\">{$parsedData['subtitle']}</p>";
        }

        if (!empty($providers)) {
            $html .= "<div class=\"providers-grid\">";

            foreach ($providers as $provider) {
                $html .= "<div class=\"provider-item\">";

                if (!empty($provider['logo'])) {
                    $html .= "<img src=\"{$provider['logo']}\" alt=\"{$provider['name']}\" class=\"provider-logo\">";
                }

                $html .= "<span class=\"provider-name\">{$provider['name']}</span>";
                $html .= "</div>";
            }

            $html .= "</div>";
        }

        if (!empty($parsedData['ctaText']) && !empty($parsedData['ctaUrl'])) {
            $html .= "<a href=\"{$parsedData['ctaUrl']}\" class=\"banner-cta btn btn-primary\">{$parsedData['ctaText']}</a>";
        }

        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }
}