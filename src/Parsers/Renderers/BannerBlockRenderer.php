<?php

namespace App\Parsers\Renderers;

use App\Parsers\Dtos\BannerBlockDto;
use App\Parsers\Dtos\BlockDtoInterface;

class BannerBlockRenderer extends BaseBlockRenderer
{
    public function render(BlockDtoInterface $dto): string
    {
        if (!$dto instanceof BannerBlockDto) {
            throw new \InvalidArgumentException('Expected BannerBlockDto');
        }

        if ($dto->context === 'sidebar') {
            return $this->renderSidebar($dto);
        }

        return $this->renderDefault($dto);
    }

    private function renderSidebar(BannerBlockDto $dto): string
    {
        $bgColor = $dto->backgroundColor;
        $textColor = $dto->textColor;

        $html = "<div class=\"banner banner-sidebar\" style=\"background-color: {$bgColor}; color: {$textColor};\">";
        $html .= "<div class=\"banner-content-sidebar\">";

        if (!empty($dto->image)) {
            $html .= "<img src=\"{$dto->image['src']}\" alt=\"" . $this->escape($dto->title) . "\" class=\"banner-image-sidebar\">";
        }

        $html .= "<h4 class=\"banner-title-sidebar\">" . $this->escape($dto->title) . "</h4>";

        if (!empty($dto->subtitle)) {
            $html .= "<p class=\"banner-subtitle-sidebar\">" . $this->escape($dto->subtitle) . "</p>";
        }

        if (!empty($dto->ctaText) && !empty($dto->ctaUrl)) {
            $html .= "<a href=\"{$dto->ctaUrl}\" class=\"banner-cta-sidebar btn btn-sm btn-primary\">" . $this->escape($dto->ctaText) . "</a>";
        }

        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }

    private function renderDefault(BannerBlockDto $dto): string
    {
        switch ($dto->bannerType) {
            case 'promo-header':
                return $this->renderPromoHeader($dto);
            case 'review-banner':
                return $this->renderReviewBanner($dto);
            case 'providers-banner':
                return $this->renderProvidersBanner($dto);
            default:
                return '';
        }
    }

    private function renderPromoHeader(BannerBlockDto $dto): string
    {
        $bgColor = $dto->backgroundColor;
        $textColor = $dto->textColor;
        $dismissClass = $dto->dismissible ? 'dismissible' : '';

        $html = "<div class=\"banner banner-promo-header {$dismissClass}\" style=\"background-color: {$bgColor}; color: {$textColor};\">";

        if ($dto->dismissible) {
            $html .= "<button class=\"banner-dismiss\" onclick=\"this.parentElement.remove()\">&times;</button>";
        }

        $html .= "<div class=\"banner-content\">";
        $html .= "<h2 class=\"banner-title\">" . $this->escape($dto->title) . "</h2>";

        if (!empty($dto->subtitle)) {
            $html .= "<p class=\"banner-subtitle\">" . $this->escape($dto->subtitle) . "</p>";
        }

        if (!empty($dto->ctaText) && !empty($dto->ctaUrl)) {
            $html .= "<a href=\"{$dto->ctaUrl}\" class=\"banner-cta btn btn-primary\">" . $this->escape($dto->ctaText) . "</a>";
        }

        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }

    private function renderReviewBanner(BannerBlockDto $dto): string
    {
        $bgColor = $dto->backgroundColor;
        $textColor = $dto->textColor;

        $html = "<div class=\"banner banner-review\" style=\"background-color: {$bgColor}; color: {$textColor};\">";
        $html .= "<div class=\"banner-content\">";

        if (!empty($dto->image)) {
            $html .= "<img src=\"{$dto->image['src']}\" alt=\"" . $this->escape($dto->title) . "\" class=\"banner-image\">";
        }

        $html .= "<div class=\"banner-text\">";
        $html .= "<h3 class=\"banner-title\">" . $this->escape($dto->title) . "</h3>";

        if ($dto->rating > 0) {
            $stars = $this->generateStars($dto->rating);
            $html .= "<div class=\"banner-rating\">";
            $html .= "<span class=\"stars\">{$stars}</span>";
            $html .= "<span class=\"rating-value\">{$dto->rating}/5</span>";

            if ($dto->reviewCount > 0) {
                $html .= "<span class=\"review-count\">({$dto->reviewCount} reviews)</span>";
            }

            $html .= "</div>";
        }

        if (!empty($dto->ctaText) && !empty($dto->ctaUrl)) {
            $html .= "<a href=\"{$dto->ctaUrl}\" class=\"banner-cta btn btn-primary\">" . $this->escape($dto->ctaText) . "</a>";
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

    private function renderProvidersBanner(BannerBlockDto $dto): string
    {
        $bgColor = $dto->backgroundColor;
        $textColor = $dto->textColor;

        $html = "<div class=\"banner banner-providers\" style=\"background-color: {$bgColor}; color: {$textColor};\">";
        $html .= "<div class=\"banner-content\">";
        $html .= "<h3 class=\"banner-title\">" . $this->escape($dto->title) . "</h3>";

        if (!empty($dto->subtitle)) {
            $html .= "<p class=\"banner-subtitle\">" . $this->escape($dto->subtitle) . "</p>";
        }

        if (!empty($dto->providers)) {
            $html .= "<div class=\"providers-grid\">";

            foreach ($dto->providers as $provider) {
                $html .= "<div class=\"provider-item\">";

                if (!empty($provider['logo'])) {
                    $html .= "<img src=\"{$provider['logo']}\" alt=\"" . $this->escape($provider['name']) . "\" class=\"provider-logo\">";
                }

                $html .= "<span class=\"provider-name\">" . $this->escape($provider['name']) . "</span>";
                $html .= "</div>";
            }

            $html .= "</div>";
        }

        if (!empty($dto->ctaText) && !empty($dto->ctaUrl)) {
            $html .= "<a href=\"{$dto->ctaUrl}\" class=\"banner-cta btn btn-primary\">" . $this->escape($dto->ctaText) . "</a>";
        }

        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }

    protected function getSupportedType(): string
    {
        return 'banner';
    }
}