<?php

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BannerBlockData;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class BannerBlockRenderer implements EmailBlockRenderer
{
    public $type = 'banner';

    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $newsletterRenderContext): RenderedBlock
    {
        if (!$blockData instanceof BannerBlockData) {
            return RenderedBlock::skipped();
        }

        return match ($blockData->bannerType) {
            'review-banner' => $this->renderReviewBanner($blockData),
            'providers-banner' => $this->renderProvidersBanner($blockData),
            default => $this->renderPromoBanner($blockData)
        };
    }

    private function renderPromoBanner(BannerBlockData $blockData): RenderedBlock
    {
        $html = [];
        $html[] = "<div style=\"background-color: {$blockData->backgroundColor}; color: {$blockData->textColor}; padding: 25px; border-radius: 8px; margin: 20px 0; position: relative;\">";

        if ($blockData->dismissible) {
            $html[] = '<div style="position: absolute; top: 10px; right: 10px; color: ' . $blockData->textColor . '; cursor: pointer; font-size: 20px; line-height: 1;">×</div>';
        }

        $html[] = "<h3 style=\"color: {$blockData->textColor}; margin: 0 0 10px 0; font-size: 24px;\">" . Str::sanitize($blockData->title) . "</h3>";

        if ($blockData->subtitle) {
            $html[] = "<p style=\"color: {$blockData->textColor}; margin: 0 0 15px 0; font-size: 16px;\">" . Str::sanitize($blockData->subtitle) . "</p>";
        }

        if ($blockData->ctaText && $blockData->ctaUrl) {
            $html[] = '<a href="' . Str::sanitize($blockData->ctaUrl) . '" style="display: inline-block; padding: 10px 20px; background-color: white; color: ' . $blockData->backgroundColor . '; text-decoration: none; border-radius: 4px; font-weight: bold;">';
            $html[] = Str::sanitize($blockData->ctaText);
            $html[] = '</a>';
        }

        $html[] = '</div>';

        return RenderedBlock::rendered(implode("\n", $html));
    }

    private function renderReviewBanner(BannerBlockData $blockData): RenderedBlock
    {
        $html = [];
        $html[] = "<div style=\"background-color: {$blockData->backgroundColor}; color: {$blockData->textColor}; padding: 25px; border-radius: 8px; margin: 20px 0; display: table; width: 100%;\">";

        if ($blockData->image && isset($blockData->image['src'])) {
            $html[] = '<div style="display: table-cell; width: 120px; vertical-align: middle; padding-right: 20px;">';
            $html[] = '<img src="' . Str::sanitize($blockData->image['src']) . '" alt="' . Str::sanitize($blockData->title) . '" style="width: 120px; height: auto; border-radius: 4px;">';
            $html[] = '</div>';
        }

        $html[] = '<div style="display: table-cell; vertical-align: middle;">';
        $html[] = "<h3 style=\"color: {$blockData->textColor}; margin: 0 0 10px 0; font-size: 22px;\">" . Str::sanitize($blockData->title) . "</h3>";

        if ($blockData->rating > 0) {
            $stars = str_repeat('★', (int)$blockData->rating) . str_repeat('☆', 5 - (int)$blockData->rating);
            $html[] = "<div style=\"color: #ffc107; font-size: 20px; margin-bottom: 5px;\">{$stars}</div>";
            $html[] = "<div style=\"color: {$blockData->textColor}; font-size: 14px; margin-bottom: 10px;\">";
            $html[] = "{$blockData->rating}/5";
            if ($blockData->reviewCount > 0) {
                $html[] = " ({$blockData->reviewCount} reviews)";
            }
            $html[] = "</div>";
        }

        if ($blockData->ctaText && $blockData->ctaUrl) {
            $html[] = '<a href="' . Str::sanitize($blockData->ctaUrl) . '" style="display: inline-block; padding: 10px 20px; background-color: white; color: ' . $blockData->backgroundColor . '; text-decoration: none; border-radius: 4px; font-weight: bold; margin-top: 10px;">';
            $html[] = Str::sanitize($blockData->ctaText);
            $html[] = '</a>';
        }

        $html[] = '</div>';
        $html[] = '</div>';

        return RenderedBlock::rendered(implode("\n", $html));
    }

    private function renderProvidersBanner(BannerBlockData $blockData): RenderedBlock
    {
        $html = [];
        $html[] = "<div style=\"background-color: {$blockData->backgroundColor}; color: {$blockData->textColor}; padding: 25px; border-radius: 8px; margin: 20px 0;\">";
        $html[] = "<h3 style=\"color: {$blockData->textColor}; margin: 0 0 10px 0; font-size: 24px; text-align: center;\">" . Str::sanitize($blockData->title) . "</h3>";

        if ($blockData->subtitle) {
            $html[] = "<p style=\"color: {$blockData->textColor}; margin: 0 0 20px 0; font-size: 16px; text-align: center;\">" . Str::sanitize($blockData->subtitle) . "</p>";
        }

        if (!empty($blockData->providers)) {
            $html[] = '<table style="width: 100%; margin: 20px 0;"><tr>';
            $providerCount = count($blockData->providers);
            $cellWidth = floor(100 / min($providerCount, 4));

            foreach (array_slice($blockData->providers, 0, 4) as $provider) {
                $html[] = "<td style=\"width: {$cellWidth}%; text-align: center; padding: 10px; vertical-align: middle;\">";
                if (!empty($provider['logo'])) {
                    $html[] = '<img src="' . Str::sanitize($provider['logo']) . '" alt="' . Str::sanitize($provider['name']) . '" style="max-width: 100px; height: auto;">';
                } else {
                    $html[] = '<div style="font-weight: bold; color: ' . $blockData->textColor . ';">' . Str::sanitize($provider['name']) . '</div>';
                }
                $html[] = '</td>';
            }

            $html[] = '</tr></table>';
        }

        if ($blockData->ctaText && $blockData->ctaUrl) {
            $html[] = '<div style="text-align: center; margin-top: 20px;">';
            $html[] = '<a href="' . Str::sanitize($blockData->ctaUrl) . '" style="display: inline-block; padding: 12px 30px; background-color: white; color: ' . $blockData->backgroundColor . '; text-decoration: none; border-radius: 4px; font-weight: bold;">';
            $html[] = Str::sanitize($blockData->ctaText);
            $html[] = '</a>';
            $html[] = '</div>';
        }

        $html[] = '</div>';

        return RenderedBlock::rendered(implode("\n", $html));
    }
}